"""Run after http_flow.py on the same disposable fixture with PHP display_errors=0."""
import csv
import json
import re
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

from http_flow import Browser, check

PDF = b"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n"


def publish(op, verify, request, revision=1):
    op.change(request, "submit", revision=revision)
    verify.change(request, "approve", revision=revision)


def document_id(body):
    return int(re.search(rb'r=document&amp;id=(\d+)">Lampiran', body)[1])


def main():
    admin, op, other_tab, verify, outsider = [Browser() for _ in range(5)]
    admin.login("demo-admin", "only-synthetic-demo-admin")
    admin.call("accounts", {"id": 4, "username": "demo-verify", "active": 1,
                            "roles[]": ["verifier", "downloader"], "units[]": [1, 2],
                            "modules[]": ["profile", "retirement", "service", "award", "dikmil"]})
    op.login("demo-op")
    other_tab.login("demo-op")
    verify.login("demo-verify")
    outsider.login("demo-out")

    profile = {"person": 1, "category_id": 1, "identifier": "0000123",
               "name": "=DATA DEMO Satu", "birth_date": "1980-01-01"}
    new_birth = op.draft(**{**profile, "birth_date": "1980-02-01"})
    new_name = op.draft(**{**profile, "name": "DATA DEMO Nama Baru"})
    publish(op, verify, new_birth)
    op.change(new_name, "submit", expected=409)
    op.change(new_name, "rebase")
    publish(op, verify, new_name, revision=2)
    body = op.call("person", query={"id": 1})[1]
    check(b"DATA DEMO Nama Baru" in body and b"1980-02-01" in body and b"Identitas wajib lengkap" in body,
          "rebase menerbitkan perubahan nama dan mempertahankan koreksi tanggal lahir")

    profile.update(name="DATA DEMO Nama Baru", birth_date="1980-02-01")
    first = op.draft(**{**profile, "name": "DATA DEMO Nama Terbit"})
    competing = op.draft(**{**profile, "name": "DATA DEMO Nama Usulan", "specialty": "DATA DEMO Bidang"})
    op.change(competing, "submit")
    publish(op, verify, first)
    verify.change(competing, "approve", expected=409)
    verify.change(competing, "return", comment="Periksa nama terbaru")
    page = op.change(competing, "revise", expected=409)[1]
    check(b'resolution[name]' in page and b"DATA DEMO Nama Terbit" in page and b"DATA DEMO Nama Usulan" in page,
          "revisi yang dikembalikan menampilkan dua pilihan untuk kolom yang bentrok")
    old_plan = op.request_tokens(competing)
    another = op.draft(**{**profile, "name": "DATA DEMO Nama Terbit Kedua"})
    publish(op, verify, another)
    op.change(competing, "revise", expected=409, **old_plan, **{"resolution[name]": "current"})
    op.change(competing, "revise", **{"resolution[name]": "current"})
    publish(op, verify, competing, revision=2)
    body = op.call("person", query={"id": 1})[1]
    check(b"DATA DEMO Nama Terbit Kedua" in body and b"DATA DEMO Bidang" in body and b"DATA DEMO Nama Usulan" not in body,
          "pilihan konflik usang ditolak, pilihan baru menjaga nilai terbit dan perubahan kolom lain")

    profile.update(name="DATA DEMO Nama Terbit Kedua", specialty="DATA DEMO Bidang")
    concurrent = op.draft(**profile)
    old_form = other_tab.request_tokens(concurrent)
    op.change(concurrent, "edit", **{**profile, "name": "DATA DEMO Tab Tersimpan"})
    stale_body = other_tab.change(concurrent, "edit", expected=409, **old_form,
                                  **{**profile, "name": "DATA DEMO Tab Lama"})[1]
    check(b'value="DATA DEMO Tab Tersimpan"' in stale_body and b'value="DATA DEMO Tab Lama"' not in stale_body,
          "edit tab lama mendapat konflik dan menampilkan draf tersimpan")
    incomplete = op.draft(unit=2, name="DATA DEMO Draf Belum Lengkap")
    draft = op.call("request", query={"id": incomplete})[1]
    check(b"Wajib sebelum diajukan" in draft and b"nomor kedinasan" in draft,
          "indikator kelengkapan membedakan draf belum lengkap")

    estimate = datetime.now(ZoneInfo("Asia/Jakarta")).date() + timedelta(days=530)
    window = {"from": str(estimate), "to": str(estimate)}
    forecast = op.draft("retirement", person=1, estimate_date=str(estimate), source="DATA DEMO Dasar")
    op.change(forecast, "submit")
    profile["name"] = "DATA DEMO Ejaan Baru"
    rename = op.draft(**profile)
    publish(op, verify, rename)
    verify.change(forecast, "approve")
    groups = json.loads(op.call("map", query=window)[1])["units"]
    exported = list(csv.DictReader(op.call("export", window)[1].decode().splitlines()))
    dashboard = op.call("dashboard", query=window)[1]
    check(sum(g["retiring"] for g in groups) == sum(g["total"] for g in groups) == len(exported) == 1
          and exported[0]["Perkiraan"] == str(estimate) and b"Perkiraan dalam filter" in dashboard,
          "jendela perkiraan pilihan cocok pada peta, dashboard dan CSV")
    for single in ({"from": str(estimate)}, {"to": str(estimate)}):
        groups = json.loads(op.call("map", query=single)[1])["units"]
        check(sum(g["retiring"] for g in groups) == 1, "filter perkiraan dengan satu batas ikut dihitung")
    groups = json.loads(op.call("map")[1])["units"]
    assert sum(g["retiring"] for g in groups) == 0

    profile["name"] = "DATA DEMO Ejaan Disempurnakan"
    publish(op, verify, op.draft(**profile))
    assert b"Perlu tinjau" not in op.call("person", query={"id": 1})[1]
    assert b"DATA DEMO Ejaan Disempurnakan" in op.call("personnel", query=window)[1]
    publish(op, verify, op.draft(**{**profile, "birth_date": "1980-03-01"}))
    check(b"Perlu tinjau" in op.call("person", query={"id": 1})[1],
          "koreksi nama mempertahankan validitas, sementara tanggal lahir menandai perlu tinjau")

    admin.call("master", {"kind": "identifier", "id": 2, "code": "NIP", "name": "NIP", "active": 0})
    rejected = outsider.draft(unit=2, category_id=2, identifier="0000999001", name="DATA DEMO Terhalang")
    body = outsider.change(rejected, "submit", expected=422)[1]
    assert b"Jenis nomor kedinasan nonaktif" in body
    lookup = outsider.call("personnel", query={"q": "0000456"})[1]
    pns = int(re.search(rb'r=person&amp;id=(\d+)">DATA DEMO Dua', lookup)[1])
    correction = outsider.draft(person=pns, category_id=2, identifier="0000456", name="DATA DEMO Dua Terkoreksi")
    publish(outsider, verify, correction)
    body = outsider.call("person", query={"id": pns})[1]
    check(b"0000456" in body and b"DATA DEMO Dua Terkoreksi" in body,
          "jenis identitas nonaktif menolak penetapan baru dan mempertahankan koreksi profil lama")
    blocked = outsider.call("new", query={"module": "dikmil", "person": pns}, expected=422)[1]
    check(b"Dikmil tidak berlaku" in body and b"Dikmil tidak berlaku" in blocked and b'module=dikmil' not in body,
          "status Dikmil tidak berlaku diturunkan dari kategori tanpa membuat riwayat fiktif")
    admin.call("master", {"kind": "identifier", "id": 2, "code": "NIP", "name": "NIP", "active": 1})

    award = op.draft("award", person=1, type_id=4, issuer="DATA DEMO Lampiran", awarded_on="2026-01-02")
    spec = {"id": award, "revision": 1}
    oversize = PDF + b"0" * (7 * 1024 * 1024)
    denied = op.call("upload", spec, expected=413, upload=("besar.pdf", oversize))[1]
    check(b"Total unggahan melebihi batas server" in denied and b"kedaluwarsa" not in denied,
          "batas POST menghasilkan 413 dengan pesan ukuran")
    old = document_id(op.call("upload", spec, upload=("lama.pdf", PDF))[1])
    old_document_form = other_tab.request_tokens(award)
    op.call("upload", {**spec, "replace_id": old}, expected=422, upload=("rusak.pdf", b"rusak"))
    assert op.call("document", query={"id": old})[1] == PDF
    replacement = PDF.replace(b"/Catalog", b"/Catalog /Fixture true")
    new = document_id(op.call("upload", {**spec, "replace_id": old}, upload=("baru.pdf", replacement))[1])
    op.call("document", query={"id": old}, expected=404)
    other_tab.change(award,"submit",expected=409,**old_document_form)
    check(new != old and op.call("document", query={"id": new})[1] == replacement,
          "penggantian tercatat sebagai berkas baru dan menolak formulir lama; kegagalan menjaga berkas lama")
    op.call("upload", {**spec, "action": "detach", "document_id": new})
    op.call("document", query={"id": new}, expected=404)
    current = document_id(op.call("upload", spec, upload=("terakhir.pdf", PDF))[1])
    op.change(award, "submit")
    op.call("upload", {**spec, "action": "detach", "document_id": current}, expected=409)
    verify.change(award, "approve")
    check(op.call("document", query={"id": current})[1] == PDF,
          "lepas lampiran hanya pada draf, persetujuan menerbitkan pilihan terakhir")

    for _ in range(41):
        Browser().login("demo-admin", "only-synthetic-demo-admin")
    for _ in range(8):
        Browser().call("login", {"username": "audit-unknown-user", "password": "salah"}, expected=401)
    Browser().login("demo-admin", "only-synthetic-demo-admin")
    Browser().call("login", {"username": "audit-unknown-user", "password": "salah"}, expected=429)
    check(True, "41 login sah dari IP sama berhasil tanpa menghapus pembatasan kegagalan")
    print("Seluruh skenario regresi P0 lulus.")


if __name__ == "__main__":
    main()

"""Run on a fresh synthetic database with demo.sql and the two test bootstrap users."""
import csv
import http.cookiejar
import json
import os
import re
import urllib.error
import urllib.parse
import urllib.request
from datetime import timedelta, datetime
from zoneinfo import ZoneInfo

BASE = os.environ.get("BINPROF_TEST_URL", "http://127.0.0.1:8710")
PASS = "only-synthetic-test-user"
CHECKS = 0


def check(condition, description):
    global CHECKS
    if not condition:
        raise AssertionError(description)
    CHECKS += 1
    print("PASS", description)


class Browser:
    def __init__(self):
        self.client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = None

    def call(self, route, data=None, expected=200, csrf=True, query=None, upload=None):
        url = BASE + "/index.php?" + urllib.parse.urlencode({"r": route, **(query or {})})
        return self.fetch(url, data, expected, csrf, upload)

    def fetch(self, url, data=None, expected=200, csrf=True, upload=None):
        headers = {}
        payload = None
        if data is not None:
            if csrf and self.token is None:
                self.call("login")
            if csrf:
                data = {**data, "csrf": self.token}
            if upload:
                boundary = "binprof-synthetic-test"
                sections = [f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
                            for key, value in data.items()]
                filename, content = upload
                sections.append(f'--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="{filename}"\r\nContent-Type: application/pdf\r\n\r\n'.encode()
                                + content + f'\r\n--{boundary}--\r\n'.encode())
                payload = b"".join(sections)
                headers["Content-Type"] = "multipart/form-data; boundary=" + boundary
            else:
                payload = urllib.parse.urlencode(data, doseq=True).encode()
        try:
            response = self.client.open(urllib.request.Request(url, data=payload, headers=headers), timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read()
            token = re.search(rb'name="csrf" value="([a-f0-9]+)"', body)
            if token:
                self.token = token[1].decode()
            assert response.status == expected, (url, response.status, expected, re.findall(rb'<p class="notice error"[^>]*>(.*?)</p>',body))
            return response.url, body

    def login(self, username, password=PASS):
        self.call("login", {"username": username, "password": password})

    def draft(self, module="profile", **values):
        url, body = self.call("new", {"module": module, **values})
        assert "Revisi 1" in body.decode()
        return int(urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)["id"][0])

    def change(self, rid, action, revision=1, expected=200, **args):
        return self.call("request", {"id": rid, "revision": revision, "action": action, **args}, expected=expected)


def main():
    admin, grantor, op, verify, outsider = [Browser() for _ in range(5)]
    admin.login("demo-admin", "only-synthetic-demo-admin")
    grantor.login("demo-grants", "only-synthetic-demo-grants")
    modules = ["profile", "dikum", "dikmil", "award", "retirement", "service"]
    op_roles = ["operator", "verifier", "exporter", "downloader"]
    for name, roles, units in [
        ("demo-op", op_roles, [1]),
        ("demo-verify", ["verifier", "downloader"], [1, 2]),
        ("demo-out", ["operator", "exporter"], [2]),
    ]:
        admin.call("accounts", {"username": name, "password": PASS, "active": 1, "roles[]": roles, "units[]": units, "modules[]": modules if "verifier" in roles else []})
    op.login("demo-op")
    verify.login("demo-verify")
    outsider.login("demo-out")
    op.call("new", {"module": "profile", "unit": 1}, expected=403, csrf=False)
    check(True, "mutasi tanpa CSRF ditolak")

    rid = op.draft(unit=1, name="=DATA DEMO Satu", category_id=1, identifier="0000123", status="approved", owner_id=4)
    marker = json.loads(op.call("map")[1])
    check(marker["units"] == [], "draf dan mass assignment tidak menerbitkan profil")
    verify.call("request", query={"id": rid}, expected=404)
    check(True, "draf privat terhadap verifikator")
    op.change(rid, "submit")
    op.change(rid, "edit", expected=409, name="DATA DEMO disusupi")
    op.change(rid, "approve", expected=403)
    outsider.call("request", query={"id": rid}, expected=404)
    check(True, "snapshot tetap, persetujuan sendiri, dan akses unit lain ditolak")
    verify.change(rid, "approve")
    check(b"0000123" in op.call("person", query={"id": 1})[1], "nomor kedinasan mempertahankan nol depan")
    outsider.call("person", query={"id": 1}, expected=404)
    check(True, "detail personel di luar cakupan ditolak")

    duplicate = op.draft(unit=1, name="DATA DEMO duplikat", category_id=1, identifier="0000123")
    op.change(duplicate, "submit")
    verify.change(duplicate, "approve", expected=409)
    check(json.loads(op.call("map")[1])["units"][0]["total"] == 1, "benturan identitas rollback tanpa personel yatim")

    second = outsider.draft(unit=2, name="DATA DEMO Dua", category_id=2, identifier="0000456")
    outsider.change(second, "submit")
    verify.change(second, "approve")
    listing = outsider.call("personnel")[1]
    second_person = int(re.search(rb'r=person&amp;id=(\d+)">DATA DEMO Dua', listing)[1])
    stale = op.draft(person=1, name="DATA DEMO Stale", category_id=1, identifier="0000123")
    correction = op.draft(person=1, name="=DATA DEMO Satu", category_id=1, identifier="0000123", birth_date="1980-01-01")
    op.change(stale,"submit")
    op.change(correction,"submit")
    verify.change(correction,"approve")
    verify.change(stale,"approve",expected=409)
    verify.change(stale,"return",comment="Dasar berubah")
    op.change(stale,"revise")
    check(b"DATA DEMO Stale" in op.call("request",query={"id": stale, "revision": 1})[1], "konflik versi dan riwayat revisi sebelumnya")

    PDF = (b"%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
           b"2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
           b"trailer\n<< /Root 1 0 R >>\n%%EOF\n")
    for year in [2010,2012]:
        edu = op.draft("dikum", person=1, type_id=2, institution="DATA DEMO Kampus", end_year=year, status="completed")
        if year == 2010:
            op.call("upload", {"id": edu, "revision": 1}, expected=422, upload=("demo.php.pdf",PDF))
            op.call("upload", {"id": edu, "revision": 1}, expected=422, upload=("demo.pdf",b"invalid format"))
            op.call("upload", {"id": edu, "revision": 1}, upload=("demo.pdf",PDF))
            verify.call("document", query={"id":1}, expected=404)
            outsider.call("document", query={"id":1}, expected=404)
            check(op.call("document", query={"id":1})[1] == PDF, "validasi berkas dan izin lampiran draf")
        op.change(edu,"submit")
        if year == 2010:
            check(verify.call("document",query={"id":1})[1] == PDF,"verifikator membaca dokumen setelah diajukan")
        verify.change(edu,"approve")
    result = json.loads(op.call("map",query={"dikum":2})[1])
    check(result["units"][0]["total"] == 1 and "0000123" not in str(result) and result["units"][0]["latitude"] is None,
          "agregasi unik, koordinat kosong dan payload minimal")
    result = op.call("export",{"dikum":2,"q":"0000123"})[1]
    check(b"0000123" in result and b"'=DATA DEMO Satu" in result and b"0000456" not in result,"CSV scoped, digit utuh dan formula dinetralkan")

    report_filter = {"history_kind":"dikum","history_q":"DEMO Kampus","completion_year":2010,"history_status":"completed"}
    report = op.call("education",query=report_filter)[1]
    exported = op.call("export",{"report":"education",**report_filter})[1]
    records = list(csv.DictReader(exported.decode().splitlines()))
    check(b"DATA DEMO Kampus" in report and b">2010</td>" in report and b">2012</td>" not in report
          and len(records) == 1 and records[0]["Selesai"] == "2010" and records[0]["Lembaga"] == "DATA DEMO Kampus",
          "rekap pendidikan dan CSV menerapkan filter pada baris yang sama serta mempertahankan presisi tahun")
    outsider_report = outsider.call("education",query=report_filter)[1]
    outsider_export = outsider.call("export",{"report":"education",**report_filter})[1]
    verify.call("export",{"report":"education",**report_filter},expected=403)
    check(b"DATA DEMO Kampus" not in outsider_report and len(outsider_export.splitlines()) == 1,
          "rekap dan CSV pendidikan menghormati cakupan satuan dan izin ekspor")
    op.call("education",query={"completion_year":"2020-01-01"},expected=422)

    military = op.draft("dikmil", person=1, type_id=3, institution="DATA DEMO Akademi",
                        start_on="2019-01-01", end_on="2018-01-01", status="completed")
    op.change(military,"submit",expected=422)
    op.change(military,"edit",type_id=3,institution="DATA DEMO Akademi",
              start_year=2019,end_year=2020,status="completed")
    op.change(military,"submit")
    verify.change(military,"approve")
    check(b"DATA DEMO Akademi" in op.call("person",query={"id":1})[1],"riwayat Dikmil dan validasi periode")
    for _ in range(2):
        award = op.draft("award",person=1,type_id=4,issuer="DATA DEMO Panitia",
                         awarded_on="2020-02-29",decision_number="DEMO-Z")
        op.change(award,"submit")
        verify.change(award,"approve")
    check(b"Potensi duplikasi" in op.call("person",query={"id":1})[1],"penghargaan terverifikasi dan peringatan duplikasi")
    unpublished = op.draft("award",person=1,type_id=4,issuer="DATA DEMO Belum Disetujui",awarded_on="2020-02-29")
    op.change(unpublished,"submit")
    award_filter = {"history_type":4,"award_from":"2020-02-29","award_to":"2020-02-29"}
    report = op.call("awards",query=award_filter)[1]
    records = list(csv.DictReader(op.call("export",{"report":"awards",**award_filter})[1].decode().splitlines()))
    check(report.count(b"DATA DEMO Panitia") == 2 and b"DATA DEMO Belum Disetujui" not in report
          and len(records) == 2 and all(r["Pemberi"] == "DATA DEMO Panitia" for r in records),
          "rekap penghargaan dan CSV memuat setiap riwayat yang sudah disetujui dalam rentang tanggal")
    op.call("awards",query={"award_from":"2020-03-01","award_to":"2020-02-29"},expected=422)

    today = datetime.now(ZoneInfo("Asia/Jakarta")).date()
    forecast = op.draft("retirement",person=1,estimate_date=str(today + timedelta(days=75)),source="DATA DEMO surat")
    check(b"Perkiraan belum ditetapkan" in op.call("person",query={"id":1})[1],"perkiraan draf tetap belum terbit")
    op.change(forecast,"submit")
    verify.change(forecast,"approve")
    admin.call("operations",{"action":"run"})
    admin.call("operations",{"action":"run"})
    inbox = op.call("notifications")[1]
    check(inbox.count("ambang 3 bulan".encode()) == 1 and b"ambang 6 bulan" not in inbox,"cron catch-up satu ambang dan deduplikasi")
    admin.call("accounts",{"id":3,"username":"demo-op","active":1,"roles[]":op_roles,"modules[]":modules,"units[]":[2]})
    op.login("demo-op")
    check(b"ambang 3 bulan" not in op.call("notifications")[1],"notifikasi lama tertutup setelah pencabutan scope")
    op.call("document",query={"id":1},expected=404)
    admin.call("accounts",{"id":3,"username":"demo-op","active":1,"roles[]":op_roles,"modules[]":modules,"units[]":[1,2]})
    op.login("demo-op")

    if os.environ.get("BINPROF_TEST_PHP"):
        import subprocess
        subprocess.run([os.environ["BINPROF_TEST_PHP"],"cli/account-recovery.php"],
                       input=b'{"id":3,"action":"case-capabilities","create":true,"cross_unit":true}',check=True)
        op.login("demo-op")
        case = op.draft("case",unit=1,category="discipline",number="DEMO-1",reported_on=str(today),
                        title="Rahasia demo",officer="DATA DEMO Petugas",stage="decision",
                        parties=f"1: pihak\n{second_person}: saksi",decision="DATA DEMO Keputusan",source="DATA DEMO Surat")
        op.change(case,"submit")
        verify.call("request",query={"id":case},expected=404)
        admin.call("case",query={"id":1},expected=404)
        grantor.call("case-grants",{"id":1,"target":2,"capability":"read","action":"give"},expected=422)
        for cap in ["read","verify","export"]:
            grantor.call("case-grants",{"id":1,"target":4,"capability":cap,"action":"give"})
        verify.change(case,"approve")
        check(b"DATA DEMO Dua" in verify.call("case",query={"id":1})[1],"kasus dengan beberapa pihak dan grant eksplisit")
        check(b"Rahasia demo" in verify.call("case-export",{"id":1})[1],"ekspor kasus dengan grant terpisah")
        grantor.call("case-grants",{"id":1,"target":4,"capability":"read","action":"revoke"})
        verify.call("case",query={"id":1},expected=404)
        check(b"Rahasia demo" not in outsider.call("person",query={"id":second_person})[1],"grant dicabut pada sesi aktif dan profil umum tanpa bocoran")

    service = op.draft("service",person=1,unit_id=2,service_status="active",
                       effective_on=str(today + timedelta(days=1)),reason="DATA DEMO mutasi")
    op.change(service,"submit")
    verify.change(service,"approve")
    admin.call("operations",{"action":"run"})
    outsider.call("person",query={"id":1},expected=404)
    check(True,"mutasi yang belum efektif mempertahankan cakupan lama")
    if os.environ.get("BINPROF_TEST_PHP"):
        subprocess.run([os.environ["BINPROF_TEST_PHP"],"tests/job_flow.php",str(today + timedelta(days=1))],check=True)
        check(b"DATA DEMO Satu" in outsider.call("person",query={"id":1})[1],"mutasi efektif berpindah cakupan")

    for state in ["archived","active"]:
        revision = op.draft("service",person=1,unit_id=2,service_status=state,
                            effective_on=str(today + timedelta(days=1)),reason="DATA DEMO pengujian arsip")
        op.change(revision,"submit")
        verify.change(revision,"approve")
        if os.environ.get("BINPROF_TEST_PHP"):
            subprocess.run([os.environ["BINPROF_TEST_PHP"],"tests/run_at.php",str(today + timedelta(days=1))],check=True)
            listing = outsider.call("personnel", query={"status":state})[1]
            check(b"DATA DEMO Satu" in listing, "arsip/pemulihan mengikuti verifikasi dan filter " + state)
    check(op.call("document",query={"id":1})[1] == PDF,"lampiran dan relasi tetap tersimpan setelah pemulihan")

    admin.call("accounts",{"id":4,"username":"demo-verify","active":1,
                           "roles[]":["verifier","downloader"],"modules[]":["award"],"units[]":[1,2]})
    check("r=login" in verify.call("personnel")[0],"perubahan hak mencabut sesi lama")
    verify.login("demo-verify")
    restricted = op.draft("dikum",person=1,type_id=2,institution="DATA DEMO Pembatasan modul",status="ongoing")
    op.change(restricted,"submit")
    verify.call("request",query={"id":restricted},expected=404)
    verify.change(restricted,"approve",expected=404)
    check(True,"verifikator tanpa modul yang ditugaskan tidak bisa melihat atau memutus")

    for path in ["/database/binprofkes.sql","/config/local.php","/storage/keys.json","/cli/cron.php"]:
        op.fetch(BASE+path,expected=404)
    check(True,"rute privat ditolak dari document root uji")

    category = {"kind":"category","code":"DEMO-EXTRA","name":"DATA DEMO Kategori Tambahan","identifier_type_id":2,"active":1}
    admin.call("master",category)
    duplicate_form = admin.call("master",{**category,"name":"DATA DEMO Nama Percobaan"},expected=409)[1]
    check(b'value="DATA DEMO Nama Percobaan"' in duplicate_form
          and b"<p class=\"notice error\"" in duplicate_form, "kode master duplikat ditolak dan input form tetap ada")
    master_a = {"kind":"unit","id":1,"code":"DEMO-A","name":"DATA DEMO - Satuan A","active":1}
    master_b = {"kind":"unit","id":2,"code":"DEMO-B","name":"DATA DEMO — Satuan B","active":1}
    admin.call("master",{**master_a,"parent_id":2})
    admin.call("master",{**master_b,"parent_id":1},expected=422)
    check(True,"hierarki satuan menolak siklus setelah hubungan induk disimpan")
    bad_coordinate = admin.call("master",{**master_b,"latitude":"91","longitude":"106"},expected=422)[1]
    check(b'value="91"' in bad_coordinate and b"koordinat yang valid" in bad_coordinate,
          "koordinat di luar rentang ditolak dan form mempertahankan nilainya")
    admin.call("master",{**master_b,"latitude":"-6.2","longitude":"106.8"})
    admin.call("master",{"kind":"award","id":4,"code":"DEMO","name":"DATA DEMO — Penghargaan","active":0})
    check(b"DATA DEMO Panitia" in op.call("awards",query={"history_type":4})[1]
          and b"DATA DEMO Kategori Tambahan" in outsider.call("new")[1],
          "master nonaktif mempertahankan riwayat dan kategori baru tersedia saat input")
    admin.call("master",{"kind":"award","id":4,"code":"DEMO","name":"DATA DEMO — Penghargaan","active":1})

    for _ in range(8):
        Browser().call("login",{"username":"absent-test","password":"bad"},expected=401)
    Browser().call("login",{"username":"absent-test","password":"bad"},expected=429)
    check(True,"rate limit login per akun/IP bersama")
    print(f"{CHECKS} pemeriksaan bernama lulus.")


if __name__ == "__main__":
    main()

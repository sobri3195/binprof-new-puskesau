"""Run after http_flow.py on the same disposable fixture with PHP ZIP and pdftotext."""
import io
import re
import subprocess
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

from openpyxl import load_workbook

from http_flow import Browser, PASS, check
from p0_regression import publish


def queue_ids(body):
    return {int(value) for value in re.findall(rb'r=request&amp;id=(\d+)', body)}


def workbook(body):
    book = load_workbook(io.BytesIO(body))
    return book.active


def pdf_text(body):
    assert body.startswith(b"%PDF-")
    return subprocess.run(
        ["pdftotext", "-", "-"], input=body, capture_output=True, check=True
    ).stdout.decode()


def preview(browser, content, module="profile", unit=1, separator="comma", expected=200, mapping=None):
    if mapping is None:
        mapping = {"name": "Nama lama", "category_id": "Kategori lama", "identifier": "No. lama"}
    body = browser.call(
        "imports", {"action": "preview", "module": module, "unit_id": unit, "separator": separator,
                    **{f"mapping[{field}]": value for field, value in mapping.items()}},
        upload=("sintetis.csv", content.encode()), expected=expected,
    )[1]
    token = re.search(rb'name="token" value="([a-f0-9]{64})"', body)
    return body, token[1].decode() if token else None


def commit(browser, token, expected=200):
    return browser.call("imports", {"action": "commit", "token": token}, expected=expected)[1]


def main():
    admin, grants, op, verifier, outsider = [Browser() for _ in range(5)]
    admin.login("demo-admin", "only-synthetic-demo-admin")
    grants.login("demo-grants", "only-synthetic-demo-grants")
    admin.call("accounts", {"id": 4, "username": "demo-verify", "active": 1,
                            "roles[]": ["verifier", "downloader"], "units[]": [1, 2],
                            "modules[]": ["profile", "dikum", "dikmil", "award", "retirement", "service"]})
    admin.call("accounts", {"username": "p1-leader", "password": PASS, "active": 1,
                            "roles[]": ["leader"], "units[]": [1]})
    leader = Browser()
    leader.login("p1-leader")
    op.login("demo-op")
    verifier.login("demo-verify")
    outsider.login("demo-out")
    today = datetime.now(ZoneInfo("Asia/Jakarta")).date()

    member = op.draft(unit=1, name="=DATA DEMO Éka & Lestari", category_id=1, identifier="000078901")
    publish(op, verifier, member)
    book = workbook(op.call("export", {"format": "xlsx", "q": "000078901"})[1])
    outsider_book = workbook(outsider.call("export", {"format": "xlsx", "q": "000078901"})[1])
    check(book["B2"].value == "000078901" and book["B2"].data_type == "s"
          and book["C2"].value == "=DATA DEMO Éka & Lestari" and book["C2"].data_type == "s"
          and outsider_book["B2"].value is None, "XLSX mempertahankan Unicode, nol awal, teks formula, dan scope")
    text = pdf_text(op.call("export", {"format": "pdf", "q": "000078901"})[1])
    check("Éka & Lestari" in text and "000078901" in text and "0000456" not in text, "PDF asli dapat dibaca dan memakai filter")
    verifier.call("export", {"format": "pdf"}, expected=403)
    verifier.call("export", {"format": "xlsx"}, expected=403)
    op.call("export", {"format": "xls"}, expected=422)
    check(True, "format ekspor baru mempertahankan izin dan menolak format asing")
    filters = {"report": "education", "history_kind": "dikum", "history_q": "DEMO Kampus",
               "completion_year": 2010}
    history = workbook(op.call("export", {**filters, "format": "xlsx"})[1])
    rows = list(history.values)
    check(len(rows) == 2 and "2010" in rows[1] and "DATA DEMO Kampus" in rows[1],
          "rekap XLSX memakai filter riwayat dan mempertahankan presisi tahun")
    verifier.call("case-export", {"id": 1, "format": "pdf"}, expected=404)
    op.call("case-export", {"id": 1, "format": "xlsx"}, expected=404)
    grants.call("case-grants", {"id": 1, "target": 4, "capability": "read", "action": "give"})
    body = verifier.call("case-export", {"id": 1, "format": "pdf"})[1]
    check("Rahasia demo" in pdf_text(body), "ekspor kasus perlu grant baca dan ekspor khusus yang aktif")

    header = "Nama lama,Kategori lama,No. lama\n"
    leader.call("imports", expected=403)
    preview(outsider, header + "DATA DEMO Di Luar,1,P1-100\n", expected=422)
    forms = outsider.call("imports")[1]
    check("Satuan A".encode() not in forms and "Satuan B".encode() in forms, "impor dibatasi ke operator dan satuan aktif dalam scope")
    _, token = preview(outsider, header + "DATA DEMO Lingkup,1,000078901\n", unit=2)
    check(token is not None, "validasi identitas saat impor menjaga kerahasiaan keberadaan profil di luar scope")
    preview(op, header + "DATA DEMO Pemetaan,1,P1-101\n", expected=422,
            mapping={"name": "Kolom tidak ada", "category_id": "Kategori lama", "identifier": "No. lama"})
    body, token = preview(op, header + "DATA DEMO Pendahulu,1,P1-102\nDATA DEMO Duplikat,1,P1-102\n=Formula,1,P1-103\n")
    check(token is None and b"Baris 3" in body and b"Baris 4" in body and b"formula" in body,
          "pratinjau melaporkan baris duplikat dan nilai mirip formula serta memblokir batch")
    preview(op, header + "".join(f"DATA DEMO {i},1,P1-LIMIT-{i}\n" for i in range(101)), expected=422)

    before = queue_ids(op.call("requests")[1])
    source = "\ufeffNama lama;Kategori lama;No. lama\nDATA DEMO Impor Satu;1;000078902\nDATA DEMO Impor Dua;2;000078903\n"
    body, token = preview(op, source, separator="semicolon")
    check(token and b"2 baris valid" in body and queue_ids(op.call("requests")[1]) == before,
          "dry-run UTF-8 BOM dengan pemetaan dan pemisah eksplisit tidak membuat pengajuan")
    body = commit(op, token)
    created = queue_ids(body) - before
    check(len(created) == 2 and b"2 draf impor dibuat" in body
          and b"DATA DEMO Impor Satu" not in op.call("personnel", query={"q": "000078902"})[1],
          "commit membuat draf dan menunggu persetujuan")
    commit(op, token, expected=409)
    body, new_token = preview(op, source, separator="semicolon")
    check(new_token is None and b"2 baris perlu diperbaiki" in body, "unggahan ulang mendeteksi draf aktif")
    publish(op, verifier, min(created))
    lookup = op.call("personnel", query={"q": "000078902"})[1]
    person = int(re.search(rb'r=person&amp;id=(\d+)">DATA DEMO Impor Satu', lookup)[1])
    assert b"DATA DEMO Impor Satu" in lookup

    before = queue_ids(op.call("requests")[1])
    _, token = preview(op, header + "DATA DEMO Rollback Satu,1,P1-ROLLBACK-1\nDATA DEMO Rollback Dua,1,P1-ROLLBACK-2\n")
    other = Browser()
    other.login("demo-op")
    competing = other.draft(unit=1, category_id=1, identifier="P1-ROLLBACK-2", name="DATA DEMO Benturan")
    publish(other, verifier, competing)
    commit(op, token, expected=409)
    check(queue_ids(op.call("requests")[1]) == before | {competing},
          "perubahan setelah pratinjau menggagalkan seluruh batch dan rollback draf pertama")

    award_mapping = {"person_id": "Personel", "type_id": "Jenis", "issuer": "Pemberi", "awarded_on": "Tanggal"}
    award = f"Personel,Jenis,Pemberi,Tanggal\n{person},4,DATA DEMO Pemberi Impor,2022-05-04\n"
    _, token = preview(op, award, module="award", mapping=award_mapping)
    before = queue_ids(op.call("requests")[1])
    created = queue_ids(commit(op, token)) - before
    publish(op, verifier, created.pop())
    wrong_unit, rejected = preview(op, award, module="award", unit=2, mapping=award_mapping)
    check(b"DATA DEMO Pemberi Impor" in op.call("awards", query={"history_q": "Pemberi Impor"})[1]
          and rejected is None and b"Personel tidak ditemukan pada satuan ini" in wrong_unit,
          "impor penghargaan memakai ID personel terverifikasi dan menolak perpindahan scope")

    education_mapping = {"person_id": "Personel", "type_id": "Jenis", "institution": "Lembaga",
                         "status": "Status", "end_year": "Tahun"}
    for module, kind in (("dikum", 2), ("dikmil", 3)):
        education = f"Personel,Jenis,Lembaga,Status,Tahun\n{person},{kind},DATA DEMO Impor {module},completed,2020\n"
        before = queue_ids(op.call("requests")[1])
        _, token = preview(op, education, module=module, mapping=education_mapping)
        created = queue_ids(commit(op, token)) - before
        publish(op, verifier, created.pop())
        history = op.call("education", query={"history_kind": module, "history_q": f"Impor {module}"})[1]
        check(f"DATA DEMO Impor {module}".encode() in history and b">2020</td>" in history,
              f"impor {module} melewati verifikasi dan mempertahankan presisi tahun")

    period = today.strftime("%Y-%m")
    next_period = (today.replace(day=28) + timedelta(days=4)).strftime("%Y-%m")
    target = {"position_title": "DATA DEMO Analis", "period": period, "unit_id": 1, "required_count": 3}
    op.call("staffing", target, expected=403)
    target_page = admin.call("staffing", target)[1]
    check(b"DATA DEMO Analis" in target_page and b">Terisi</th>" not in target_page,
          "administrator tanpa hak baca dapat mengelola target tanpa melihat angka personel")
    admin.call("staffing", {**target, "required_count": 2})
    admin.call("staffing", {**target, "unit_id": 2, "position_title": "DATA DEMO Jabatan B"})
    admin.call("staffing", {**target, "period": next_period})
    service = {"unit_id": 1, "position_title": target["position_title"], "effective_on": str(today), "reason": "DATA DEMO"}
    publish(op, verifier, op.draft("service", person=person, service_status="active", **service))
    body = leader.call("staffing", query={"period": period})[1]
    cells = rb"DATA DEMO Analis</th><td>2</td>\s*<td>1</td><td>1</td>\s*<td>0</td>"
    check(re.search(cells, body) and b"Jabatan B" not in body, "target diperbarui dan hitungan personel mengikuti scope serta status aktif")
    publish(op, verifier, op.draft("service", person=person, service_status="archived", **service))
    body = leader.call("staffing", query={"period": period})[1]
    future = leader.call("staffing", query={"period": next_period})[1]
    check(re.search(rb"Analis</th><td>2</td>\s*<td>0</td><td>2</td>", body)
          and "Analis</th><td>3</td>" in future.decode() and "<td>—</td>" in future.decode(),
          "arsip dikeluarkan dari kebutuhan terisi dan bulan depan hanya menampilkan target")


if __name__ == "__main__":
    main()

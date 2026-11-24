"""Synthetic Playwright check against the same database after tests/http_flow.py."""
import os
from playwright.sync_api import sync_playwright

base = os.environ.get("BINPROF_TEST_URL", "http://127.0.0.1:8710")
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(
        executable_path=os.environ.get("CHROMIUM_BIN", "/usr/bin/chromium"),
        args=["--no-sandbox"])
    for width, height in [(1440, 900), (390, 844)]:
        page = browser.new_page(viewport={"width": width, "height": height})
        errors = []
        page.on("pageerror", lambda error: errors.append(str(error)))
        page.goto(base + "/login")
        page.keyboard.press("Tab")
        assert page.locator(":focus").text_content() == "Lewati ke isi"
        page.get_by_label("Nama pengguna").fill("demo-op")
        page.get_by_label("Kata sandi").fill("only-synthetic-test-user")
        page.get_by_role("button", name="Masuk", exact=True).click()
        page.get_by_role("heading", name="Dashboard pimpinan").wait_for()
        page.locator(".leaflet-container").wait_for()
        page.locator('#map svg path:not([d="M0 0"])').first.wait_for()
        page.get_by_role("link", name="DATA DEMO — Satuan B", exact=True).wait_for()
        assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth")
        page.get_by_role("button", name="Fokus peta: DATA DEMO — Satuan B").click()
        page.locator(".leaflet-popup").get_by_role("link", name="Lihat personel satuan").wait_for()
        page.get_by_role("button", name="Reset tampilan").click()
        page.locator(".leaflet-popup").wait_for(state="detached")
        if width < 500:
            page.get_by_text("Navigasi", exact=True).click()
        nav = page.get_by_role("navigation",name="Navigasi seluler" if width < 500 else "Navigasi utama")
        nav.get_by_role("link",name="Pendidikan",exact=True).click()
        page.get_by_role("heading",name="Rekap pendidikan").wait_for()
        page.get_by_role("cell",name="DATA DEMO Kampus",exact=True).first.wait_for()
        if width < 500:
            page.get_by_text("Navigasi", exact=True).click()
        nav.get_by_role("link",name="Personel",exact=True).click()
        page.get_by_role("heading", name="Personel", exact=True).wait_for()
        assert not errors, errors
        print(f"PASS Chromium {width}×{height}: login, fokus dan reset peta, rekap, tabel, dan navigasi.")
        if width > 500:
            page.route("**/assets/region.geojson",lambda route: route.abort())
            page.goto(base + "/dashboard")
            page.get_by_role("status").filter(has_text="Latar peta lokal gagal dimuat").wait_for()
            page.get_by_role("button",name="Fokus peta: DATA DEMO — Satuan B").click()
            page.locator(".leaflet-popup").get_by_role("link",name="Lihat personel satuan").click()
            page.get_by_role("heading",name="Personel",exact=True).wait_for()
            page.get_by_role("link",name="DATA DEMO Dua",exact=True).wait_for()
            print("PASS Chromium: kegagalan latar lokal tetap menyediakan marker dan daftar personel.")
        page.close()
    browser.close()

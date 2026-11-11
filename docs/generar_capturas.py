#!/usr/bin/env python3
"""Genera las capturas de pantalla usadas en los manuales.

Requisitos:
  - MySQL/MariaDB iniciado y base de_todito_reyes importada.
  - Servidor PHP en http://127.0.0.1:8000 (ver manual de instalación).
  - Playwright para Python y Google Chrome instalados.
  - Para las capturas de phpMyAdmin, Apache iniciado en el puerto 80.

Ejecutar desde la carpeta docs/:

    python generar_capturas.py
"""

from __future__ import annotations

from pathlib import Path

from playwright.sync_api import Page, sync_playwright

BASE_URL = "http://127.0.0.1:8000"
PMA_URL = "http://localhost/phpmyadmin"
OUT = Path(__file__).resolve().parent / "capturas"
VIEWPORT = {"width": 1680, "height": 1000}


def login(page: Page, usuario: str, clave: str) -> None:
    page.goto(BASE_URL + "/", wait_until="networkidle")
    page.fill('input[name="usuario_login"]', usuario)
    page.fill('input[name="password"]', clave)
    page.click('button[type="submit"]')
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(250)


def cerrar_aviso_bienvenida(page: Page) -> None:
    """Cierra el flash de bienvenida para que no tape la primera tarjeta."""
    boton = page.locator(".alert-ok .alert-close")
    if boton.count():
        try:
            boton.first.click()
            page.wait_for_timeout(150)
        except Exception:
            pass


def capturar(page: Page, nombre: str, *, full_page: bool = False, selector: str | None = None) -> None:
    destino = OUT / nombre
    if selector:
        page.locator(selector).first.screenshot(path=str(destino))
    else:
        if full_page:
            # La barra lateral es sticky y mide 100vh; en una captura de página
            # completa se estira para acompañar todo el contenido.
            page.add_style_tag(
                content=".sidebar{position:static!important;height:auto!important;}"
            )
            page.wait_for_timeout(150)
        page.screenshot(path=str(destino), full_page=full_page)
    print("  ", nombre)


def capturas_login(browser) -> None:
    contexto = browser.new_context(viewport=VIEWPORT, device_scale_factor=1)
    page = contexto.new_page()
    page.goto(BASE_URL + "/", wait_until="networkidle")
    page.fill('input[name="usuario_login"]', "admin")
    page.fill('input[name="password"]', "admin123")
    capturar(page, "00_login.png", selector=".login-card")
    contexto.close()


def capturas_admin(browser) -> None:
    contexto = browser.new_context(viewport=VIEWPORT, device_scale_factor=1)
    page = contexto.new_page()
    login(page, "admin", "admin123")
    cerrar_aviso_bienvenida(page)
    capturar(page, "01_panel_admin.png", full_page=True)

    page.goto(BASE_URL + "/productos", wait_until="networkidle")
    capturar(page, "02_productos_admin.png", full_page=True)

    page.goto(BASE_URL + "/clientes", wait_until="networkidle")
    capturar(page, "03_clientes_admin.png")

    page.goto(BASE_URL + "/facturacion", wait_until="networkidle")
    capturar(page, "04_historial_ventas.png")

    page.goto(BASE_URL + "/facturacion/nueva", wait_until="networkidle")
    # Carrito de ejemplo: dos productos y un cliente, sin emitir la factura.
    page.locator("[data-agregar]").nth(0).click()
    page.locator("[data-agregar]").nth(1).click()
    page.select_option('select[name="id_cliente"]', index=1)
    page.wait_for_timeout(300)
    capturar(page, "05_pos.png")

    page.goto(BASE_URL + "/facturacion/ver/2", wait_until="networkidle")
    capturar(page, "06_comprobante_admin.png", selector="main")

    page.goto(BASE_URL + "/tasas", wait_until="networkidle")
    capturar(page, "07_tasas_admin.png")

    page.goto(BASE_URL + "/mermas", wait_until="networkidle")
    capturar(page, "08_mermas_admin.png")

    page.goto(BASE_URL + "/usuarios", wait_until="networkidle")
    capturar(page, "09_usuarios_admin.png")

    contexto.close()


def capturas_cajero(browser) -> None:
    contexto = browser.new_context(viewport=VIEWPORT, device_scale_factor=1)
    page = contexto.new_page()
    login(page, "mcajera", "cajera123")
    cerrar_aviso_bienvenida(page)
    capturar(page, "10_panel_cajero.png", full_page=True)

    page.goto(BASE_URL + "/clientes", wait_until="networkidle")
    capturar(page, "11_clientes_cajero.png")

    page.goto(BASE_URL + "/facturacion/nueva", wait_until="networkidle")
    page.locator("[data-agregar]").nth(0).click()
    page.locator("[data-agregar]").nth(1).click()
    page.select_option('select[name="id_cliente"]', index=1)
    page.wait_for_timeout(300)
    capturar(page, "12_pos_cajero.png")

    page.goto(BASE_URL + "/facturacion/ver/2", wait_until="networkidle")
    capturar(page, "13_comprobante_cajero.png", selector="main")

    page.goto(BASE_URL + "/facturacion", wait_until="networkidle")
    capturar(page, "14_historial_cajero.png")

    page.goto(BASE_URL + "/mermas", wait_until="networkidle")
    capturar(page, "15_mermas_cajero.png")

    page.goto(BASE_URL + "/tasas", wait_until="networkidle")
    capturar(page, "16_tasas_cajero.png")

    contexto.close()


def capturas_phpmyadmin(browser) -> None:
    contexto = browser.new_context(viewport=VIEWPORT, device_scale_factor=1)
    page = contexto.new_page()
    try:
        page.goto(
            PMA_URL + "/index.php?route=/database/import&db=de_todito_reyes",
            wait_until="commit",
            timeout=30000,
        )
        page.wait_for_timeout(2500)
        capturar(page, "17_phpmyadmin_importar.png", full_page=True)

        page.goto(
            PMA_URL + "/index.php?route=/database/structure&db=de_todito_reyes",
            wait_until="commit",
            timeout=30000,
        )
        page.wait_for_timeout(2000)
        capturar(page, "18_phpmyadmin_estructura.png", full_page=True)
    except Exception as exc:
        print("  phpMyAdmin no disponible:", exc)
    finally:
        contexto.close()


def main() -> None:
    OUT.mkdir(exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=True)
        print("Captura del login")
        capturas_login(browser)
        print("Capturas del ADMIN")
        capturas_admin(browser)
        print("Capturas del CAJERO")
        capturas_cajero(browser)
        print("Capturas de phpMyAdmin")
        capturas_phpmyadmin(browser)
        browser.close()
    print(f"Listo. Capturas en {OUT}")


if __name__ == "__main__":
    main()

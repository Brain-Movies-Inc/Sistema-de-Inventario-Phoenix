#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generador de diagramas de casos de uso (SVG) para el sistema De Todito Reyes.
No requiere librerías externas: solo la biblioteca estándar de Python.
Ejecutar:  python generar_diagramas.py
"""

from html import escape
from pathlib import Path
import math

OUT = Path(__file__).resolve().parent

# ---------------------------------------------------------------------------
# Paleta y estilos
# ---------------------------------------------------------------------------
ROLES = {
    "admin": {
        "fill": "#DBEAFE", "stroke": "#2563EB", "text": "#1E3A8A",
        "actor_fill": "#EFF6FF", "actor_stroke": "#1D4ED8", "line": "#3B82F6",
    },
    "cajero": {
        "fill": "#DCFCE7", "stroke": "#16A34A", "text": "#14532D",
        "actor_fill": "#F0FDF4", "actor_stroke": "#15803D", "line": "#22C55E",
    },
    "shared": {
        "fill": "#E2E8F0", "stroke": "#64748B", "text": "#0F172A",
        "actor_fill": "#F8FAFC", "actor_stroke": "#475569", "line": "#94A3B8",
    },
}

FONT = "Segoe UI, Arial, sans-serif"
INK = "#0F172A"
MUTED = "#475569"
BOUNDARY = "#334155"
INCLUDE = "#7C3AED"
EXTEND = "#EA580C"


def esc(value: str) -> str:
    return escape(str(value), quote=True)


def text(x, y, value, size=14, fill=INK, weight="normal", anchor="middle", extra=""):
    return (
        f'<text x="{x}" y="{y}" font-family="{FONT}" font-size="{size}" '
        f'fill="{fill}" font-weight="{weight}" text-anchor="{anchor}" {extra}>'
        f"{esc(value)}</text>"
    )


def multiline(cx, cy, lines, size=13, fill=INK, weight="normal", line_height=17):
    if isinstance(lines, str):
        lines = [lines]
    total = len(lines)
    start = cy - (total - 1) * line_height / 2 + size * 0.35
    out = [
        f'<text font-family="{FONT}" font-size="{size}" fill="{fill}" '
        f'font-weight="{weight}" text-anchor="middle">'
    ]
    for i, line in enumerate(lines):
        out.append(f'<tspan x="{cx}" y="{start + i * line_height}">{esc(line)}</tspan>')
    out.append("</text>")
    return "".join(out)


class Diagram:
    def __init__(self, width, height, title, subtitle=""):
        self.width = width
        self.height = height
        self.bg = [
            f'<rect x="0" y="0" width="{width}" height="{height}" fill="#FFFFFF"/>',
            text(width / 2, 36, title, size=22, weight="bold", fill="#1E293B"),
        ]
        if subtitle:
            self.bg.append(text(width / 2, 60, subtitle, size=13, fill=MUTED))
        self.lines = []
        self.nodes = []
        self.labels = []

    def add_bg(self, value):
        self.bg.append(value)

    def add_line(self, value):
        self.lines.append(value)

    def add_node(self, value):
        self.nodes.append(value)

    def add_label(self, value):
        self.labels.append(value)

    def save(self, filename):
        defs = f"""
<defs>
  <marker id="arrow-include" viewBox="0 0 10 10" refX="9" refY="5"
          markerWidth="7" markerHeight="7" orient="auto-start-reverse">
    <path d="M 0 0 L 10 5 L 0 10 z" fill="{INCLUDE}"/>
  </marker>
  <marker id="arrow-extend" viewBox="0 0 10 10" refX="9" refY="5"
          markerWidth="7" markerHeight="7" orient="auto-start-reverse">
    <path d="M 0 0 L 10 5 L 0 10 z" fill="{EXTEND}"/>
  </marker>
</defs>"""
        svg = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            f'<svg xmlns="http://www.w3.org/2000/svg" width="{self.width}" '
            f'height="{self.height}" viewBox="0 0 {self.width} {self.height}">\n'
            f"{defs}\n"
            + "\n".join(self.bg)
            + "\n"
            + "\n".join(self.lines)
            + "\n"
            + "\n".join(self.nodes)
            + "\n"
            + "\n".join(self.labels)
            + "\n</svg>\n"
        )
        (OUT / filename).write_text(svg, encoding="utf-8")
        print(f"Generado: {filename}")


def boundary(d, x, y, w, h, title):
    d.add_bg(
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="18" '
        f'fill="#FFFFFF" stroke="{BOUNDARY}" stroke-width="2"/>'
    )
    d.add_label(text(x + w / 2, y + 30, title, size=16, weight="bold", fill=BOUNDARY))


def section(d, x, y, w, h, title):
    d.add_bg(
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="12" '
        f'fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.4" stroke-dasharray="6 4"/>'
    )
    d.add_label(text(x + 14, y + 24, title, size=13, weight="bold", fill=MUTED, anchor="start"))


def usecase(d, cx, cy, lines, role="shared", width=None):
    if isinstance(lines, str):
        lines = [lines]
    if width is None:
        width = max(180, min(310, max(len(line) for line in lines) * 7.7 + 40))
    height = 52 + (len(lines) - 1) * 18
    style = ROLES[role]
    d.add_node(
        f'<g><ellipse cx="{cx}" cy="{cy}" rx="{width / 2}" ry="{height / 2}" '
        f'fill="{style["fill"]}" stroke="{style["stroke"]}" stroke-width="1.6"/>'
        + multiline(cx, cy, lines, size=13, fill=style["text"])
        + "</g>"
    )
    return {"cx": cx, "cy": cy, "w": width, "h": height}


def actor(d, cx, cy, label, sublabel, role="shared"):
    style = ROLES[role]
    d.add_node(
        f'<g stroke="{style["actor_stroke"]}" stroke-width="2" fill="none" '
        f'stroke-linecap="round">'
        f'<circle cx="{cx}" cy="{cy - 46}" r="11" fill="{style["actor_fill"]}"/>'
        f'<path d="M {cx} {cy - 35} L {cx} {cy + 5}"/>'
        f'<path d="M {cx - 23} {cy - 22} L {cx + 23} {cy - 22}"/>'
        f'<path d="M {cx} {cy + 5} L {cx - 20} {cy + 38}"/>'
        f'<path d="M {cx} {cy + 5} L {cx + 20} {cy + 38}"/>'
        f"</g>"
        + text(cx, cy + 62, label, size=14, weight="bold", fill=style["actor_stroke"])
        + text(cx, cy + 80, sublabel, size=12, fill=MUTED)
    )
    return (cx, cy)


def assoc(d, actor_pt, uc, role="shared", curve=None):
    style = ROLES[role]
    ax, ay = actor_pt
    if curve == "right":
        path = (
            f'M {ax} {ay} C {ax} {ay + 130}, {uc["cx"] - 250} {ay + 130}, '
            f'{uc["cx"]} {uc["cy"]}'
        )
        d.add_line(
            f'<path d="{path}" fill="none" stroke="{style["line"]}" '
            f'stroke-width="1.2" opacity="0.62"/>'
        )
    elif curve == "left":
        path = (
            f'M {ax} {ay} C {ax} {ay + 130}, {uc["cx"] + 250} {ay + 130}, '
            f'{uc["cx"]} {uc["cy"]}'
        )
        d.add_line(
            f'<path d="{path}" fill="none" stroke="{style["line"]}" '
            f'stroke-width="1.2" opacity="0.62"/>'
        )
    else:
        d.add_line(
            f'<line x1="{ax}" y1="{ay}" x2="{uc["cx"]}" y2="{uc["cy"]}" '
            f'stroke="{style["line"]}" stroke-width="1.2" opacity="0.62"/>'
        )


def edge_point(uc, target_x, target_y):
    dx = target_x - uc["cx"]
    dy = target_y - uc["cy"]
    if dx == 0 and dy == 0:
        return uc["cx"], uc["cy"]
    rx = uc["w"] / 2
    ry = uc["h"] / 2
    factor = 1 / math.sqrt((dx / rx) ** 2 + (dy / ry) ** 2)
    return uc["cx"] + dx * factor, uc["cy"] + dy * factor


def arrow(d, source, target, label, kind="include", label_t=0.5):
    color = INCLUDE if kind == "include" else EXTEND
    marker = "arrow-include" if kind == "include" else "arrow-extend"
    sx, sy = edge_point(source, target["cx"], target["cy"])
    ex, ey = edge_point(target, source["cx"], source["cy"])
    # Un pequeño desplazamiento para que la punta quede visible sobre el borde.
    vx, vy = ex - sx, ey - sy
    length = math.hypot(vx, vy) or 1
    ex += vx / length * 2
    ey += vy / length * 2
    d.add_label(
        f'<line x1="{sx}" y1="{sy}" x2="{ex}" y2="{ey}" stroke="{color}" '
        f'stroke-width="1.6" stroke-dasharray="7 5" marker-end="url(#{marker})"/>'
    )
    mx, my = sx + (ex - sx) * label_t, sy + (ey - sy) * label_t
    d.add_label(
        text(
            mx, my - 6, label, size=11, fill=color, weight="bold",
            extra='paint-order="stroke" stroke="#FFFFFF" stroke-width="5"'
        )
    )


def note(d, x, y, width, lines, title=None):
    if isinstance(lines, str):
        lines = [lines]
    height = 24 + len(lines) * 18 + (18 if title else 0)
    d.add_label(
        f'<rect x="{x}" y="{y}" width="{width}" height="{height}" rx="8" '
        f'fill="#FEFCE8" stroke="#CA8A04" stroke-width="1.4"/>'
    )
    yy = y + 22
    if title:
        d.add_label(text(x + 12, yy, title, size=12, weight="bold", fill="#854D0E", anchor="start"))
        yy += 18
    for line in lines:
        d.add_label(text(x + 12, yy, line, size=12, fill="#713F12", anchor="start"))
        yy += 18


def legend(d, x, y):
    d.add_label(
        f'<rect x="{x}" y="{y}" width="430" height="82" rx="8" fill="#FFFFFF" '
        f'stroke="#CBD5E1" stroke-width="1"/>'
    )
    d.add_label(text(x + 14, y + 22, "Referencias", size=12, weight="bold", fill=MUTED, anchor="start"))
    items = [
        ("admin", "Solo ADMIN"),
        ("shared", "Ambos roles"),
        ("cajero", "Solo CAJERO"),
    ]
    for i, (role, label) in enumerate(items):
        style = ROLES[role]
        cy = y + 38 + i * 15
        d.add_label(
            f'<ellipse cx="{x + 24}" cy="{cy}" rx="10" ry="6" '
            f'fill="{style["fill"]}" stroke="{style["stroke"]}" stroke-width="1.2"/>'
        )
        d.add_label(text(x + 42, cy + 4, label, size=11, fill=MUTED, anchor="start"))


# ---------------------------------------------------------------------------
# 1. Diagrama general
# ---------------------------------------------------------------------------
def diagrama_general():
    d = Diagram(
        1500, 1120,
        "Diagrama general de casos de uso — Sistema De Todito Reyes",
        "Diferenciación de roles: ADMIN (administrador) y CAJERO",
    )
    boundary(d, 180, 230, 1140, 740, "Sistema De Todito Reyes — Gestión y facturación")
    section(d, 220, 285, 280, 650, "Solo ADMIN")
    section(d, 540, 285, 420, 650, "Casos compartidos")
    section(d, 1000, 285, 280, 650, "Solo CAJERO")

    admin_ucs = [
        usecase(d, 360, 350, "Consultar panel\nadministrativo", "admin"),
        usecase(d, 360, 440, "Consultar productos", "admin"),
        usecase(d, 360, 530, "Gestionar productos", "admin"),
        usecase(d, 360, 620, "Editar / eliminar\nclientes", "admin"),
        usecase(d, 360, 710, "Anular / eliminar\nfacturas", "admin"),
        usecase(d, 360, 800, "Registrar / corregir\ntasa BCV", "admin"),
        usecase(d, 360, 890, "Gestionar usuarios", "admin"),
    ]
    shared_ucs = [
        usecase(d, 750, 350, "Iniciar sesión", "shared"),
        usecase(d, 750, 430, "Cerrar sesión", "shared"),
        usecase(d, 750, 510, "Consultar y registrar\nclientes", "shared"),
        usecase(d, 750, 590, "Emitir factura (POS)", "shared"),
        usecase(d, 750, 670, "Consultar historial\ny comprobantes", "shared"),
        usecase(d, 750, 750, "Exportar historial CSV", "shared"),
        usecase(d, 750, 830, "Consultar tasa BCV", "shared"),
        usecase(d, 750, 910, "Registrar / consultar\nmermas", "shared"),
    ]
    cashier_ucs = [
        usecase(d, 1140, 350, "Consultar panel\npersonal", "cajero"),
        usecase(d, 1140, 430, "Ver ventas del día\ny del mes", "cajero"),
        usecase(d, 1140, 510, "Ver últimas facturas\npropias", "cajero"),
    ]

    admin = actor(d, 300, 130, "ADMIN", "Administrador", "admin")
    cashier = actor(d, 1200, 130, "CAJERO", "Cajero", "cajero")

    for uc in admin_ucs:
        assoc(d, admin, uc, "admin")
    for uc in shared_ucs:
        assoc(d, admin, uc, "admin", curve="right")
    for uc in cashier_ucs:
        assoc(d, cashier, uc, "cajero")
    for uc in shared_ucs:
        assoc(d, cashier, uc, "cajero", curve="left")

    note(
        d, 1020, 590, 240,
        [
            "El CAJERO no accede a:",
            "• Productos",
            "• Usuarios",
            "• Registro de tasas",
            "• Anulación / eliminación",
            "  de facturas",
            "• Edición / eliminación",
            "  de clientes",
        ],
    )
    legend(d, 500, 1010)
    d.save("casos_uso_general.svg")


# ---------------------------------------------------------------------------
# 2. Diagrama detallado del ADMIN
# ---------------------------------------------------------------------------
def diagrama_admin():
    d = Diagram(
        1100, 2140,
        "Casos de uso del rol ADMIN — De Todito Reyes",
        "El administrador dispone de todos los módulos y de las tareas de configuración",
    )
    boundary(d, 240, 80, 820, 2000, "Sistema De Todito Reyes — Vista del ADMIN")
    section(d, 280, 140, 740, 250, "Acceso y panel")
    section(d, 280, 420, 740, 340, "Clientes")
    section(d, 280, 790, 740, 250, "Productos")
    section(d, 280, 1070, 740, 430, "Facturación")
    section(d, 280, 1530, 740, 500, "Tasas BCV, mermas y usuarios")

    ucs = [
        usecase(d, 650, 200, "Iniciar sesión", "admin"),
        usecase(d, 650, 290, "Cerrar sesión", "admin"),
        usecase(d, 650, 380, "Consultar panel administrativo", "admin"),

        usecase(d, 650, 480, "Consultar clientes", "admin"),
        usecase(d, 650, 570, "Registrar cliente", "admin"),
        usecase(d, 650, 660, "Editar cliente", "admin"),
        usecase(d, 650, 750, "Eliminar cliente", "admin"),

        usecase(d, 650, 850, "Consultar productos", "admin"),
        usecase(d, 650, 940, ["Gestionar productos", "crear, editar,", "activar/desactivar, eliminar"], "admin"),
        usecase(d, 650, 1030, "Consultar stock bajo", "admin"),

        usecase(d, 650, 1130, "Emitir factura (POS)", "admin"),
        usecase(d, 650, 1220, ["Consultar historial de ventas", "filtros y exportación CSV"], "admin"),
        usecase(d, 650, 1310, ["Consultar comprobante", "PDF, PNG e impresión"], "admin"),
        usecase(d, 650, 1400, ["Anular factura", "devuelve stock"], "admin"),
        usecase(d, 650, 1490, "Eliminar factura anulada", "admin"),

        usecase(d, 650, 1590, "Consultar tasa BCV", "admin"),
        usecase(d, 650, 1680, "Registrar / corregir tasa BCV", "admin"),
        usecase(d, 650, 1770, ["Registrar merma", "descuenta stock"], "admin"),
        usecase(d, 650, 1860, ["Consultar mermas", "incluye costos y pérdidas"], "admin"),
        usecase(d, 650, 1950, ["Gestionar usuarios", "crear, editar, resetear,", "activar/desactivar, eliminar"], "admin"),
    ]

    admin = actor(d, 100, 1000, "ADMIN", "Administrador", "admin")
    for uc in ucs:
        assoc(d, admin, uc, "admin")

    note(
        d, 300, 2000, 700,
        ["Recuperación de acceso: script de consola tools/crear_admin.php (fuera de la web)."],
        title="Tarea administrativa especial",
    )
    d.save("casos_uso_admin.svg")


# ---------------------------------------------------------------------------
# 3. Diagrama detallado del CAJERO
# ---------------------------------------------------------------------------
def diagrama_cajero():
    d = Diagram(
        1400, 1850,
        "Casos de uso del rol CAJERO — De Todito Reyes",
        "El cajero opera el punto de venta y consulta la información necesaria para vender",
    )
    boundary(d, 240, 80, 1100, 1680, "Sistema De Todito Reyes — Vista del CAJERO")
    section(d, 280, 140, 1020, 330, "Acceso y panel")
    section(d, 280, 510, 1020, 250, "Clientes")
    section(d, 280, 800, 1020, 520, "Facturación")
    section(d, 280, 1360, 1020, 300, "Tasas BCV y mermas")

    login = usecase(d, 600, 200, "Iniciar sesión", "cajero")
    logout = usecase(d, 600, 290, "Cerrar sesión", "cajero")
    panel = usecase(d, 600, 380, "Consultar panel personal", "cajero")
    ventas = usecase(d, 1010, 380, "Ver ventas del día y del mes", "cajero")

    consultar_cli = usecase(d, 600, 570, "Consultar clientes", "cajero")
    registrar_cli = usecase(d, 600, 660, "Registrar cliente", "cajero")

    emitir = usecase(d, 600, 860, "Emitir factura (POS)", "cajero")
    historial = usecase(d, 600, 950, "Consultar historial de ventas", "cajero")
    comprobante = usecase(d, 600, 1040, "Consultar comprobante", "cajero")
    pdf_png = usecase(d, 1010, 1090, "Descargar PDF / PNG", "cajero")
    imprimir = usecase(d, 1010, 1210, "Imprimir comprobante", "cajero")
    csv = usecase(d, 1010, 950, "Exportar resumen / detalle CSV", "cajero")

    tasa = usecase(d, 600, 1410, "Consultar tasa BCV (solo lectura)", "cajero")
    merma_reg = usecase(d, 600, 1500, ["Registrar merma", "descuenta stock"], "cajero")
    merma_ver = usecase(d, 600, 1590, ["Consultar mermas", "sin costos ni pérdidas"], "cajero")

    cajero = actor(d, 100, 820, "CAJERO", "Cajero", "cajero")
    for uc in [login, logout, panel, consultar_cli, registrar_cli, emitir,
               historial, comprobante, tasa, merma_reg, merma_ver]:
        assoc(d, cajero, uc, "cajero")

    arrow(d, panel, ventas, "<<include>>", "include")
    arrow(d, comprobante, pdf_png, "<<extend>>", "extend")
    arrow(d, comprobante, imprimir, "<<extend>>", "extend")
    arrow(d, historial, csv, "<<extend>>", "extend")

    note(
        d, 800, 1400, 460,
        [
            "Sin acceso a Productos, Usuarios, registro",
            "de tasas, anulación/eliminación de facturas",
            "ni edición/eliminación de clientes.",
        ],
        title="Restricciones del rol",
    )
    d.save("casos_uso_cajero.svg")


# ---------------------------------------------------------------------------
# 4. Proceso compartido de facturación
# ---------------------------------------------------------------------------
def diagrama_facturacion():
    d = Diagram(
        1400, 900,
        "Proceso compartido: emisión de factura — ADMIN y CAJERO",
        "Casos de uso incluidos y extendidos del punto de venta (POS)",
    )
    boundary(d, 280, 60, 1080, 800, "Punto de venta — Emisión de factura")

    usuario = actor(d, 120, 150, "ADMIN / CAJERO", "Usuario autenticado", "shared")

    emitir = usecase(d, 700, 150, "Emitir factura (POS)", "shared")
    seleccionar = usecase(d, 430, 300, ["Seleccionar cliente", "y productos"], "shared")
    calcular = usecase(d, 700, 300, ["Calcular IVA, IGTF", "y totales"], "shared")
    stock = usecase(d, 970, 300, ["Validar y descontar", "stock"], "shared")
    generar = usecase(d, 1000, 470, ["Generar consecutivo", "y comprobante"], "shared")
    pdf = usecase(d, 1180, 620, ["Descargar PDF / PNG"], "shared")
    imprimir = usecase(d, 780, 620, ["Imprimir comprobante"], "shared")

    assoc(d, usuario, emitir, "shared")
    for uc in [seleccionar, calcular, stock]:
        arrow(d, emitir, uc, "<<include>>", "include")
    arrow(d, emitir, generar, "<<include>>", "include", label_t=0.8)
    arrow(d, generar, pdf, "<<extend>>", "extend")
    arrow(d, generar, imprimir, "<<extend>>", "extend")

    note(
        d, 350, 680, 700,
        [
            "El servidor recalcula precios, IVA, IGTF y totales: el navegador solo envía",
            "id_producto y cantidad. El stock se valida y descuenta en una transacción.",
            "El consecutivo fiscal y el código de verificación se generan bajo bloqueo.",
        ],
        title="Reglas del proceso",
    )
    d.save("casos_uso_facturacion.svg")


if __name__ == "__main__":
    diagrama_general()
    diagrama_admin()
    diagrama_cajero()
    diagrama_facturacion()

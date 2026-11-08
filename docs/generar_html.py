#!/usr/bin/env python3
"""Genera docs/index.html a partir de los manuales en Markdown y los SVG.

No requiere dependencias externas: usa solo la biblioteca estándar.
Ejecutar desde la carpeta docs/:

    python generar_html.py
"""

from __future__ import annotations

import base64
import html
import re
import unicodedata
from datetime import date
from pathlib import Path

BASE = Path(__file__).resolve().parent

DOCS = [
    {
        "id": "manual-usuario",
        "file": "MANUAL_USUARIO.md",
        "label": "Manual de usuario",
        "badge": "USO",
        "description": "Operación diaria del sistema para ADMIN y CAJERO.",
    },
    {
        "id": "manual-instalacion",
        "file": "MANUAL_INSTALACION.md",
        "label": "Manual de instalación",
        "badge": "INFRA",
        "description": "Requisitos, despliegue, respaldo y endurecimiento.",
    },
    {
        "id": "revision-tecnica",
        "file": "REVISION_TECNICA.md",
        "label": "Revisión técnica",
        "badge": "AUDITORÍA",
        "description": "Hallazgos, riesgos y plan de acción recomendado.",
    },
]

DIAGRAMS = [
    ("casos_uso_general.svg", "Diagrama general de casos de uso",
     "Vista completa con la frontera del sistema y los dos actores."),
    ("casos_uso_admin.svg", "Casos de uso del ADMIN",
     "Módulos exclusivos del administrador y sus relaciones."),
    ("casos_uso_cajero.svg", "Casos de uso del CAJERO",
     "Operación de caja, consultas y límites de permisos."),
    ("casos_uso_facturacion.svg", "Proceso de facturación",
     "Flujo compartido del punto de venta, con include y extend."),
]

LINK_MAP = {
    "MANUAL_USUARIO.md": "#manual-usuario",
    "MANUAL_INSTALACION.md": "#manual-instalacion",
    "REVISION_TECNICA.md": "#revision-tecnica",
    "README.md": "#indice",
    "casos-de-uso/": "#diagramas",
    "casos-de-uso": "#diagramas",
}


def esc(text: str) -> str:
    return html.escape(text, quote=False)


def slugify(text: str, used: set[str]) -> str:
    plain = unicodedata.normalize("NFKD", text)
    plain = "".join(c for c in plain if not unicodedata.combining(c))
    plain = re.sub(r"[^a-zA-Z0-9]+", "-", plain).strip("-").lower()
    if not plain:
        plain = "seccion"
    candidate = plain
    n = 2
    while candidate in used:
        candidate = f"{plain}-{n}"
        n += 1
    used.add(candidate)
    return candidate


def map_href(href: str) -> tuple[str, str]:
    clean = html.unescape(href)
    if clean in LINK_MAP:
        return LINK_MAP[clean], ""
    for key, value in LINK_MAP.items():
        if clean.startswith(key):
            return value, ""
    if clean.startswith(("http://", "https://")):
        return href, ' target="_blank" rel="noopener"'
    return href, ""


class Markdown:
    def __init__(self, used_ids: set[str] | None = None) -> None:
        self.headings: list[tuple[int, str, str]] = []
        self.used_ids: set[str] = used_ids if used_ids is not None else set()

    # ------------------------------------------------------------------ inline
    def inline(self, text: str) -> str:
        codes: dict[str, str] = {}

        def keep_code(match: re.Match[str]) -> str:
            token = f"\x00C{len(codes)}\x00"
            codes[token] = f"<code>{html.escape(match.group(1))}</code>"
            return token

        text = re.sub(r"`([^`]+)`", keep_code, text)
        text = esc(text)

        def image(match: re.Match[str]) -> str:
            alt, src, title = match.group(1), match.group(2), match.group(3)
            title_attr = f' title="{title}"' if title else ""
            return f'<img src="{src}" alt="{alt}"{title_attr}>'

        text = re.sub(r'!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)', image, text)

        def link(match: re.Match[str]) -> str:
            label, href, title = match.group(1), match.group(2), match.group(3)
            mapped, extra = map_href(href)
            title_attr = f' title="{title}"' if title else ""
            return f'<a href="{mapped}"{title_attr}{extra}>{label}</a>'

        text = re.sub(r'\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)', link, text)

        text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
        text = re.sub(r"(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)", r"<em>\1</em>", text)
        text = re.sub(r"~~(.+?)~~", r"<del>\1</del>", text)

        for token, markup in codes.items():
            text = text.replace(token, markup)

        return text

    # ------------------------------------------------------------------ blocks
    def convert(self, source: str) -> str:
        lines = source.replace("\r\n", "\n").replace("\r", "\n").split("\n")
        return self.blocks(lines)

    def blocks(self, lines: list[str]) -> str:
        out: list[str] = []
        i = 0
        n = len(lines)

        while i < n:
            line = lines[i]

            if not line.strip():
                i += 1
                continue

            # Bloque de código con cercas
            fence = re.match(r"^(\s*)```([\w+-]*)\s*$", line)
            if fence:
                indent = len(fence.group(1))
                language = fence.group(2)
                i += 1
                code: list[str] = []
                while i < n and not re.match(r"^\s*```\s*$", lines[i]):
                    raw = lines[i]
                    code.append(raw[indent:] if raw.startswith(" " * indent) else raw.lstrip())
                    i += 1
                if i < n:
                    i += 1
                css = f' class="language-{language}"' if language else ""
                out.append(f"<pre><code{css}>{html.escape(chr(10).join(code))}</code></pre>")
                continue

            # Encabezados
            heading = re.match(r"^(\s*)(#{1,6})\s+(.*?)\s*#*\s*$", line)
            if heading:
                level = len(heading.group(2))
                title = heading.group(3)
                plain = re.sub(r"[`*_]", "", title)
                hid = slugify(plain, self.used_ids)
                self.headings.append((level, hid, plain))
                out.append(f'<h{level} id="{hid}">{self.inline(title)}</h{level}>')
                i += 1
                continue

            # Regla horizontal
            if re.match(r"^\s*([-*_])(\s*\1){2,}\s*$", line):
                out.append("<hr>")
                i += 1
                continue

            # Cita
            if re.match(r"^\s*>", line):
                quote: list[str] = []
                while i < n and re.match(r"^\s*>", lines[i]):
                    quote.append(re.sub(r"^\s*>\s?", "", lines[i]))
                    i += 1
                out.append(f"<blockquote>{self.blocks(quote)}</blockquote>")
                continue

            # Tabla
            if "|" in line and i + 1 < n and re.match(
                r"^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)+\|?\s*$", lines[i + 1]
            ):
                table, i = self.table(lines, i)
                out.append(table)
                continue

            # Listas
            item = re.match(r"^(\s*)([-*+]|\d+[.)])\s+(.*)$", line)
            if item:
                ordered = bool(re.match(r"\d", item.group(2)))
                rendered, i = self.list_block(lines, i, len(item.group(1)), ordered)
                out.append(rendered)
                continue

            # Párrafo
            paragraph: list[str] = []
            while i < n:
                current = lines[i]
                if not current.strip():
                    break
                if self.starts_block(lines, i):
                    break
                # Se conservan los espacios finales para detectar saltos duros (dos espacios).
                paragraph.append(current.lstrip(" \t").rstrip("\t"))
                i += 1
            text = "\n".join(paragraph)
            parts = re.split(r" {2,}\n", text)
            out.append("<p>" + "<br>\n".join(self.inline(part) for part in parts) + "</p>")

        return "\n".join(out)

    def starts_block(self, lines: list[str], i: int) -> bool:
        line = lines[i]
        if re.match(r"^\s*```", line):
            return True
        if re.match(r"^\s*#{1,6}\s+", line):
            return True
        if re.match(r"^\s*([-*_])(\s*\1){2,}\s*$", line):
            return True
        if re.match(r"^\s*>", line):
            return True
        if re.match(r"^\s*([-*+]|\d+[.)])\s+", line):
            return True
        if "|" in line and i + 1 < len(lines) and re.match(
            r"^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)+\|?\s*$", lines[i + 1]
        ):
            return True
        return False

    def table(self, lines: list[str], i: int) -> tuple[str, int]:
        header = [cell.strip() for cell in lines[i].strip().strip("|").split("|")]
        separator = [cell.strip() for cell in lines[i + 1].strip().strip("|").split("|")]
        aligns: list[str] = []
        for cell in separator:
            left = cell.startswith(":")
            right = cell.endswith(":")
            if left and right:
                aligns.append("center")
            elif right:
                aligns.append("right")
            else:
                aligns.append("left")

        i += 2
        rows: list[list[str]] = []
        while i < len(lines) and lines[i].strip() and "|" in lines[i]:
            rows.append([cell.strip() for cell in lines[i].strip().strip("|").split("|")])
            i += 1

        def row_html(cells: list[str], tag: str) -> str:
            parts = []
            for index, cell in enumerate(cells):
                align = aligns[index] if index < len(aligns) else "left"
                style = f"text-align:{align}"
                # Los códigos de hallazgo (CR-01, AL-02...) no deben partirse.
                if re.fullmatch(r"[A-Z]{2,4}-\d{1,3}", cell.strip()):
                    style += ";white-space:nowrap"
                parts.append(f'<{tag} style="{style}">{self.inline(cell)}</{tag}>')
            return "<tr>" + "".join(parts) + "</tr>"

        head = row_html(header, "th")
        body = "".join(row_html(row, "td") for row in rows)
        return f"<table><thead><tr>{head[4:-5]}</tr></thead><tbody>{body}</tbody></table>", i

    def list_block(
        self, lines: list[str], i: int, base_indent: int, ordered: bool
    ) -> tuple[str, int]:
        tag = "ol" if ordered else "ul"
        items: list[str] = []
        n = len(lines)

        while i < n:
            match = re.match(r"^(\s*)([-*+]|\d+[.)])\s+(.*)$", lines[i])
            if not match:
                break
            indent = len(match.group(1))
            if indent != base_indent:
                break

            marker = match.group(2)
            content = match.group(3)
            after_marker = lines[i][indent + len(marker):]
            spaces = len(after_marker) - len(after_marker.lstrip(" "))
            content_indent = indent + len(marker) + max(spaces, 1)

            item_lines = [content]
            i += 1

            while i < n:
                current = lines[i]
                if not current.strip():
                    look = i + 1
                    while look < n and not lines[look].strip():
                        look += 1
                    if look < n:
                        next_line = lines[look]
                        next_indent = len(next_line) - len(next_line.lstrip(" "))
                        next_item = re.match(r"^(\s*)([-*+]|\d+[.)])\s+", next_line)
                        if next_indent >= content_indent or (
                            next_item and len(next_item.group(1)) == base_indent
                        ):
                            item_lines.append("")
                            i += 1
                            continue
                    break

                current_indent = len(current) - len(current.lstrip(" "))
                if current_indent >= content_indent:
                    item_lines.append(current[content_indent:])
                    i += 1
                elif current_indent > base_indent:
                    item_lines.append(current[min(len(current), base_indent + 2):])
                    i += 1
                else:
                    break

            task = re.match(r"^\[([ xX])\]\s+(.*)$", content)
            prefix = ""
            if task:
                checked = " checked" if task.group(1).lower() == "x" else ""
                prefix = f'<input type="checkbox" disabled{checked}> '
                item_lines[0] = task.group(2)

            inner = self.blocks(item_lines)
            if prefix:
                if inner.startswith("<p>"):
                    inner = "<p>" + prefix + inner[3:]
                else:
                    inner = prefix + inner
            if inner.startswith("<p>") and inner.endswith("</p>") and inner.count("<p>") == 1:
                inner = inner[3:-4]
            items.append(f"<li>{inner}</li>")

        return f"<{tag}>" + "".join(items) + f"</{tag}>", i


def data_uri(path: Path) -> str:
    payload = base64.b64encode(path.read_bytes()).decode("ascii")
    return f"data:image/svg+xml;base64,{payload}"


def toc_html(sections: list[tuple[str, str, list[tuple[int, str, str]]]]) -> str:
    parts = ['<ul class="toc-docs">']
    for doc_id, label, headings in sections:
        parts.append(f'<li><a class="toc-doc" href="#{doc_id}">{esc(label)}</a>')
        subs = [(level, hid, text) for level, hid, text in headings if level == 2]
        if subs:
            parts.append('<ul class="toc-subs">')
            for _, hid, text in subs:
                parts.append(f'<li><a href="#{hid}">{esc(text)}</a></li>')
            parts.append("</ul>")
        parts.append("</li>")
    parts.append('<li><a class="toc-doc" href="#diagramas">Diagramas de casos de uso</a></li>')
    parts.append("</ul>")
    return "\n".join(parts)


def build() -> None:
    rendered: list[str] = []
    sections: list[tuple[str, str, list[tuple[int, str, str]]]] = []
    used_ids: set[str] = {doc["id"] for doc in DOCS} | {"diagramas", "indice"}

    for index, doc in enumerate(DOCS, start=1):
        source = (BASE / doc["file"]).read_text(encoding="utf-8")
        converter = Markdown(used_ids)
        body = converter.convert(source)
        sections.append((doc["id"], doc["label"], converter.headings))
        rendered.append(
            f'<section class="doc" id="{doc["id"]}">'
            f'<div class="doc-kicker">Documento {index} de {len(DOCS)} · {esc(doc["badge"])}</div>'
            f"{body}"
            f'<p class="back-top"><a href="#indice">↑ Volver al contenido</a></p>'
            f"</section>"
        )

    diagram_parts = []
    for filename, title, caption in DIAGRAMS:
        path = BASE / "casos-de-uso" / filename
        diagram_parts.append(
            f'<figure class="diagram">'
            f'<figcaption><strong>{esc(title)}</strong><span>{esc(caption)}</span></figcaption>'
            f'<img src="{data_uri(path)}" alt="{esc(title)}">'
            f'<p class="download"><a href="casos-de-uso/{filename}" target="_blank" rel="noopener">'
            f"Abrir SVG en tamaño completo</a></p>"
            f"</figure>"
        )

    diagrams = (
        '<section class="doc diagrams" id="diagramas">'
        '<div class="doc-kicker">Anexo visual · UML</div>'
        "<h1>Diagramas de casos de uso</h1>"
        "<p>Los diagramas diferencian explícitamente los dos roles del sistema. "
        "El color azul identifica al <strong>ADMIN</strong> y el verde al "
        "<strong>CAJERO</strong>; las relaciones <code>&lt;&lt;include&gt;&gt;</code> "
        "se dibujan en violeta y las <code>&lt;&lt;extend&gt;&gt;</code> en naranja.</p>"
        "<p class=\"print-note\">Para imprimir un diagrama con todo su detalle, abre su SVG "
        "y usa tamaño A3 o una impresión a escala. En A4 el diagrama se ajusta a la página "
        "y el texto puede quedar pequeño.</p>"
        '<div class="legend">'
        '<span class="chip admin">ADMIN</span>'
        '<span class="chip cajero">CAJERO</span>'
        '<span class="chip include">&lt;&lt;include&gt;&gt;</span>'
        '<span class="chip extend">&lt;&lt;extend&gt;&gt;</span>'
        "</div>"
        + "".join(diagram_parts)
        + '<p class="back-top"><a href="#indice">↑ Volver al contenido</a></p>'
        "</section>"
    )

    today = date.today().strftime("%d/%m/%Y")
    nav = toc_html(sections)

    document = f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documentación — Sistema De Todito Reyes</title>
<style>
:root {{
  --ink: #0f172a;
  --muted: #475569;
  --line: #cbd5e1;
  --soft: #f8fafc;
  --blue: #2563eb;
  --blue-dark: #1e3a8a;
  --blue-soft: #dbeafe;
  --green: #16a34a;
  --green-dark: #14532d;
  --green-soft: #dcfce7;
  --violet: #7c3aed;
  --orange: #ea580c;
  --amber-soft: #fefce8;
}}
* {{ box-sizing: border-box; }}
html {{ scroll-behavior: smooth; }}
body {{
  margin: 0;
  color: var(--ink);
  background: #eef2f7;
  font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  font-size: 16px;
  line-height: 1.65;
}}
a {{ color: var(--blue); text-decoration: none; }}
a:hover {{ text-decoration: underline; }}

/* ---------------------------------------------------------------- sidebar */
.sidebar {{
  position: fixed;
  inset: 0 auto 0 0;
  width: 300px;
  padding: 28px 22px 40px;
  background: #0f172a;
  color: #e2e8f0;
  overflow-y: auto;
  z-index: 20;
}}
.sidebar .brand {{
  font-size: 13px;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: #93c5fd;
  margin-bottom: 4px;
}}
.sidebar h1 {{
  font-size: 21px;
  line-height: 1.25;
  margin: 0 0 18px;
  color: #fff;
}}
.sidebar .meta {{
  font-size: 12px;
  color: #94a3b8;
  border-top: 1px solid #1e293b;
  border-bottom: 1px solid #1e293b;
  padding: 12px 0;
  margin-bottom: 18px;
}}
.sidebar .meta strong {{ color: #e2e8f0; font-weight: 600; }}
.sidebar h2 {{
  font-size: 11px;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: #94a3b8;
  margin: 0 0 10px;
}}
.toc-docs, .toc-subs {{ list-style: none; margin: 0; padding: 0; }}
.toc-docs > li {{ margin-bottom: 10px; }}
.toc-doc {{
  display: block;
  color: #f1f5f9;
  font-weight: 600;
  font-size: 14px;
  padding: 6px 10px;
  border-radius: 6px;
}}
.toc-doc:hover {{ background: #1e293b; text-decoration: none; }}
.toc-subs {{ margin: 2px 0 8px 10px; border-left: 1px solid #334155; }}
.toc-subs a {{
  display: block;
  color: #cbd5e1;
  font-size: 12.5px;
  padding: 3px 10px;
  line-height: 1.4;
}}
.toc-subs a:hover {{ color: #fff; text-decoration: none; }}
.sidebar .note {{
  margin-top: 24px;
  font-size: 11.5px;
  color: #94a3b8;
  line-height: 1.5;
}}

/* ------------------------------------------------------------------- main */
main {{
  margin-left: 300px;
  padding: 0 0 60px;
}}
.cover {{
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 70px clamp(30px, 7vw, 90px);
  background:
    radial-gradient(circle at 85% 12%, rgba(37, 99, 235, .16), transparent 38%),
    radial-gradient(circle at 10% 90%, rgba(22, 163, 74, .14), transparent 36%),
    #fff;
  border-bottom: 1px solid var(--line);
}}
.cover .eyebrow {{
  font-size: 13px;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: var(--blue);
  font-weight: 700;
}}
.cover h1 {{
  font-size: clamp(38px, 6vw, 64px);
  line-height: 1.05;
  letter-spacing: -.02em;
  margin: 14px 0 10px;
  max-width: 900px;
}}
.cover .subtitle {{
  font-size: clamp(18px, 2.2vw, 25px);
  color: var(--muted);
  max-width: 820px;
  margin: 0 0 34px;
}}
.cover .roles {{ display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 34px; }}
.cover .roles span {{
  padding: 7px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: .05em;
}}
.cover .roles .admin {{ background: var(--blue-soft); color: var(--blue-dark); }}
.cover .roles .cajero {{ background: var(--green-soft); color: var(--green-dark); }}
.cover .docs {{
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 14px;
  max-width: 980px;
}}
.cover .docs a {{
  display: block;
  padding: 18px 20px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  color: var(--ink);
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}}
.cover .docs a:hover {{ border-color: var(--blue); text-decoration: none; }}
.cover .docs b {{ display: block; font-size: 15px; margin-bottom: 4px; }}
.cover .docs small {{ color: var(--muted); font-size: 12.5px; line-height: 1.4; display: block; }}
.cover .date {{ margin-top: 36px; color: var(--muted); font-size: 13px; }}

.doc {{
  max-width: 1000px;
  margin: 0 auto;
  padding: 56px clamp(24px, 5vw, 64px) 30px;
  background: #fff;
  border-bottom: 1px solid var(--line);
}}
.doc-kicker {{
  font-size: 11px;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: var(--blue);
  font-weight: 700;
  margin-bottom: 8px;
}}
.doc h1 {{
  font-size: 36px;
  line-height: 1.15;
  letter-spacing: -.01em;
  margin: 0 0 22px;
  padding-bottom: 16px;
  border-bottom: 2px solid var(--blue-soft);
}}
.doc h2 {{
  font-size: 25px;
  line-height: 1.25;
  margin: 46px 0 14px;
  padding-top: 6px;
  color: var(--blue-dark);
}}
.doc h3 {{
  font-size: 19px;
  margin: 30px 0 10px;
  color: var(--ink);
}}
.doc h4 {{ font-size: 16px; margin: 22px 0 8px; }}
.doc p {{ margin: 0 0 14px; }}
.doc ul, .doc ol {{ margin: 0 0 16px; padding-left: 26px; }}
.doc li {{ margin: 4px 0; }}
.doc li > p {{ margin: 0 0 6px; }}
.doc li > ul, .doc li > ol {{ margin: 6px 0 8px; }}
.doc strong {{ color: #0b1220; }}
.doc hr {{ border: 0; border-top: 1px solid var(--line); margin: 34px 0; }}
.doc blockquote {{
  margin: 18px 0;
  padding: 14px 18px;
  background: var(--amber-soft);
  border-left: 4px solid #ca8a04;
  border-radius: 0 8px 8px 0;
  color: #422006;
}}
.doc blockquote p:last-child {{ margin-bottom: 0; }}
.doc code {{
  font-family: "Cascadia Mono", Consolas, "Courier New", monospace;
  font-size: .88em;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 1px 5px;
}}
.doc pre {{
  background: #0f172a;
  color: #e2e8f0;
  padding: 16px 18px;
  border-radius: 9px;
  overflow-x: auto;
  margin: 16px 0 20px;
  line-height: 1.5;
}}
.doc pre code {{
  background: none;
  border: 0;
  padding: 0;
  color: inherit;
  font-size: 13px;
}}
.doc table {{
  width: 100%;
  border-collapse: collapse;
  margin: 18px 0 24px;
  font-size: 14.5px;
}}
.doc th, .doc td {{
  border: 1px solid var(--line);
  padding: 9px 11px;
  vertical-align: top;
}}
.doc th {{
  background: var(--soft);
  text-align: left;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: #334155;
}}
.doc tbody tr:nth-child(even) {{ background: #fbfdff; }}
.doc input[type="checkbox"] {{ margin-right: 6px; }}
.back-top {{ margin-top: 34px; font-size: 13px; }}
.back-top a {{ color: var(--muted); }}

/* --------------------------------------------------------------- diagrams */
.diagrams {{ max-width: 1180px; }}
.diagrams h1 {{ border-bottom-color: var(--green-soft); }}
.print-note {{
  font-size: 13px;
  color: var(--muted);
  background: var(--soft);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 10px 14px;
}}
.legend {{ display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0 30px; }}
.chip {{
  font-size: 12px;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 999px;
}}
.chip.admin {{ background: var(--blue-soft); color: var(--blue-dark); }}
.chip.cajero {{ background: var(--green-soft); color: var(--green-dark); }}
.chip.include {{ background: #ede9fe; color: #4c1d95; }}
.chip.extend {{ background: #ffedd5; color: #9a3412; }}
.diagram {{
  margin: 0 0 40px;
  padding: 20px;
  background: var(--soft);
  border: 1px solid var(--line);
  border-radius: 12px;
}}
.diagram figcaption {{ margin-bottom: 12px; }}
.diagram figcaption strong {{ display: block; font-size: 17px; }}
.diagram figcaption span {{ color: var(--muted); font-size: 13.5px; }}
.diagram img {{ width: 100%; height: auto; display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; }}
.download {{ margin: 10px 0 0; font-size: 12.5px; }}

/* ------------------------------------------------------------------ print */
@media (max-width: 900px) {{
  .sidebar {{ position: static; width: auto; height: auto; }}
  .sidebar .toc-subs {{ columns: 2; }}
  main {{ margin-left: 0; }}
  .cover {{ min-height: auto; padding-top: 46px; padding-bottom: 46px; }}
}}
@media print {{
  @page {{ size: A4; margin: 16mm 15mm; }}
  body {{ background: #fff; font-size: 10.5pt; line-height: 1.45; }}
  .sidebar {{ display: none; }}
  main {{ margin: 0; padding: 0; }}
  .cover {{
    min-height: 0;
    height: 247mm;
    page-break-after: always;
    padding: 0;
    background: #fff;
    border: 0;
    justify-content: center;
  }}
  .cover h1 {{ font-size: 34pt; }}
  .cover .subtitle {{ font-size: 14pt; }}
  .cover .docs {{ grid-template-columns: 1fr 1fr; }}
  .doc {{
    max-width: none;
    padding: 0;
    border: 0;
    page-break-before: always;
  }}
  .doc:first-of-type {{ page-break-before: auto; }}
  .doc h1 {{ font-size: 22pt; }}
  .doc h2 {{ font-size: 15pt; margin-top: 22pt; page-break-after: avoid; }}
  .doc h3 {{ font-size: 12pt; page-break-after: avoid; }}
  .doc pre, .doc table, .doc blockquote, .doc figure {{ page-break-inside: avoid; }}
  .doc pre {{
    font-size: 8.5pt;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
  }}
  .doc table {{ font-size: 9pt; }}
  .doc th, .doc td {{ padding: 5px 6px; }}
  .doc a {{ color: var(--ink); text-decoration: none; }}
  .back-top {{ display: none; }}
  .diagrams {{ page-break-before: always; }}
  .diagram {{
    page-break-inside: avoid;
    page-break-after: always;
    border: 0;
    padding: 0;
    background: #fff;
  }}
  .diagram:last-of-type {{ page-break-after: auto; }}
  .diagram img {{
    border: 0;
    width: auto;
    max-width: 100%;
    max-height: 238mm;
    margin: 0 auto;
  }}
  .download {{ display: none; }}
  .print-note {{ display: none; }}
  .legend {{ margin-bottom: 12pt; }}
}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="brand">De Todito Reyes</div>
  <h1>Documentación del sistema</h1>
  <div class="meta">
    <strong>Versión 1.0</strong><br>
    Manuales, revisión técnica y diagramas UML<br>
    Generado el {today}
  </div>
  <h2>Contenido</h2>
  {nav}
  <p class="note">Documento consolidado para lectura y impresión. Los archivos Markdown y SVG originales se conservan en esta misma carpeta.</p>
</aside>

<main>
  <section class="cover" id="indice">
    <div class="eyebrow">Documentación consolidada</div>
    <h1>Sistema De Todito Reyes</h1>
    <p class="subtitle">Gestión de inventario, clientes y facturación en bolívares con tasa BCV, IVA e IGTF.</p>
    <div class="roles">
      <span class="admin">ADMIN · control total</span>
      <span class="cajero">CAJERO · punto de venta</span>
    </div>
    <div class="docs">
      {''.join(f'<a href="#{doc["id"]}"><b>{esc(doc["label"])}</b><small>{esc(doc["description"])}</small></a>' for doc in DOCS)}
      <a href="#diagramas"><b>Diagramas de casos de uso</b><small>Vista general y por rol, con include y extend.</small></a>
    </div>
    <p class="date">Documento generado el {today} · Versión 1.0</p>
  </section>

  {''.join(rendered)}
  {diagrams}
</main>
</body>
</html>
"""

    (BASE / "index.html").write_text(document, encoding="utf-8")
    print(f"Generado: {BASE / 'index.html'}")


if __name__ == "__main__":
    build()

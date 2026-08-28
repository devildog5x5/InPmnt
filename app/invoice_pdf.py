"""Professional one-page invoice PDF (Helvetica + optional JPEG logo). No extra deps."""
from __future__ import annotations

import os
from pathlib import Path
from typing import Any

# WinAnsi Helvetica widths (1/1000 em), public-domain FPDF tables.
_HELV = [
    278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
    556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
    1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
    667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
    333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
    556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 278,
]
_HELV_B = [
    278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
    556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
    975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
    667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
    333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
    611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 278,
]


def _w(bold: bool, ch: str, size: float) -> float:
    o = ord(ch)
    if o == 0x00B7:
        o = 46  # period-width stand-in
    if o < 32 or o > 126:
        o = 63  # ?
    table = _HELV_B if bold else _HELV
    return table[o - 32] * size / 1000.0


def _esc(text: str) -> str:
    out = []
    for ch in text.replace("\r", " ").replace("\n", " "):
        o = ord(ch)
        if ch in "\\()":
            out.append("\\" + ch)
        elif o == 0x2014:
            out.append("\\227")
        elif o == 0x00B7:
            out.append("\xb7")
        elif o in (0x2018, 0x2019):
            out.append("'")
        elif o in (0x201C, 0x201D):
            out.append("(" if o == 0x201C else ")")
        elif 32 <= o <= 126:
            out.append(ch)
        else:
            out.append("?")
    return "".join(out)


def _rgb(hex_color: str) -> str:
    h = hex_color.lstrip("#")
    r, g, b = int(h[0:2], 16) / 255.0, int(h[2:4], 16) / 255.0, int(h[4:6], 16) / 255.0
    return f"{r:.3f} {g:.3f} {b:.3f}"


def logo_path() -> Path | None:
    root = Path(__file__).resolve().parent.parent
    for p in (
        root / "static" / "img" / "inpmnt-logo-invoice.jpg",
        root / "php" / "static" / "img" / "inpmnt-logo-invoice.jpg",
    ):
        if p.is_file() and os.access(p, os.R_OK):
            return p
    return None


def _jpeg_size(data: bytes) -> tuple[int, int]:
    """Read SOF width/height from a baseline or progressive JPEG."""
    i = 0
    n = len(data)
    while i < n - 8:
        if data[i] != 0xFF:
            i += 1
            continue
        marker = data[i + 1]
        if marker in (0xD8, 0xD9, 0x01) or 0xD0 <= marker <= 0xD7:
            i += 2
            continue
        seglen = (data[i + 2] << 8) | data[i + 3]
        if marker in (0xC0, 0xC1, 0xC2, 0xC3):
            height = (data[i + 5] << 8) | data[i + 6]
            width = (data[i + 7] << 8) | data[i + 8]
            return width, height
        i += 2 + max(seglen, 0)
    return 160, 160


def _money(n: float) -> str:
    return f"${n:,.2f}"


def pdf_filename(number: str) -> str:
    safe = "".join(ch if ch.isalnum() or ch in "._-" else "_" for ch in (number or "invoice"))
    safe = safe.strip("._") or "invoice"
    if not safe.lower().endswith(".pdf"):
        safe += ".pdf"
    return safe


def mention_attachment(body: str) -> str:
    text = body or ""
    if "pdf copy of this invoice" in text.lower():
        return text
    return text.rstrip() + "\n\nA PDF copy of this invoice is attached.\n"


def invoice_pdf_payload(inv: Any, settings: Any | None) -> dict[str, Any]:
    """Normalize invoice + settings rows into build_invoice_pdf keys."""
    data = _as_dict(inv)
    st = _as_dict(settings)
    amount = float(data.get("amount") or 0)
    paid = float(data.get("amount_paid") or 0)
    due = round(amount - paid, 2)
    return {
        "business_name": st.get("business_name") or "InPmnt",
        "number": data.get("number") or "INV-0000",
        "client_name": data.get("client_name") or "Client",
        "client_company": data.get("client_company") or "",
        "client_email": data.get("client_email") or "",
        "client_phone": data.get("client_phone") or "",
        "title": data.get("title") or "Services",
        "notes": data.get("notes") or "",
        "issue_date": data.get("issue_date") or "",
        "due_date": data.get("due_date") or "",
        "status": data.get("status") or "sent",
        "amount": _money(amount),
        "amount_paid": _money(paid),
        "amount_due": _money(due),
        "owner_name": st.get("owner_name") or "",
        "business_email": st.get("email") or "",
        "business_phone": st.get("phone") or "",
        "website": st.get("website") or "",
        "currency": st.get("currency") or "USD",
    }


def _as_dict(row: Any) -> dict[str, Any]:
    if row is None:
        return {}
    if isinstance(row, dict):
        return dict(row)
    keys = getattr(row, "keys", None)
    if callable(keys):
        return {k: row[k] for k in keys()}
    return dict(row)


def build_invoice_pdf(data: dict[str, Any]) -> bytes:
    """Return a one-page letter PDF. Keys documented in InvoicePdf PHP twin."""
    W, H = 612.0, 792.0
    ops: list[str] = []

    def fill_rect(x: float, y: float, w: float, h: float, color: str) -> None:
        ops.append(f"{_rgb(color)} rg {x:.2f} {y:.2f} {w:.2f} {h:.2f} re f")

    def text(x: float, y: float, s: str, *, size: float = 10, bold: bool = False, color: str = "15202b") -> None:
        font = "F2" if bold else "F1"
        ops.append(
            f"BT /{font} {size:.1f} Tf {_rgb(color)} rg 1 0 0 1 {x:.2f} {y:.2f} Tm ({_esc(s)}) Tj ET"
        )

    def text_right(x_right: float, y: float, s: str, *, size: float = 10, bold: bool = False, color: str = "15202b") -> None:
        tw = sum(_w(bold, c, size) for c in s)
        text(x_right - tw, y, s, size=size, bold=bold, color=color)

    def hline(x: float, y: float, w: float, color: str = "e2e8ee", t: float = 0.6) -> None:
        fill_rect(x, y, w, t, color)

    biz = str(data.get("business_name") or "InPmnt")
    number = str(data.get("number") or "INV-0000")
    client = str(data.get("client_name") or "Client")
    company = str(data.get("client_company") or "")
    client_email = str(data.get("client_email") or "")
    client_phone = str(data.get("client_phone") or "")
    title = str(data.get("title") or "Services")
    notes = str(data.get("notes") or "")
    issue = str(data.get("issue_date") or "")
    due = str(data.get("due_date") or "")
    status = str(data.get("status") or "sent").upper()
    amount = str(data.get("amount") or "$0.00")
    paid = str(data.get("amount_paid") or "$0.00")
    due_amt = str(data.get("amount_due") or amount)
    owner = str(data.get("owner_name") or "")
    biz_email = str(data.get("business_email") or "")
    biz_phone = str(data.get("business_phone") or "")
    website = str(data.get("website") or "")
    currency = str(data.get("currency") or "USD")

    # Letterhead: teal rule + company logo top-left on white (not a dark bar).
    fill_rect(0, H - 8, W, 8, "0d6b66")
    jpeg = b""
    img_w = img_h = 0
    logo_pt = 88.0
    logo_x = 40.0
    logo_y = 680.0  # 88pt tall → top at 768, 16pt under the teal rule
    lp = logo_path()
    if lp:
        jpeg = lp.read_bytes()
        img_w, img_h = _jpeg_size(jpeg)
        ops.append(f"q {logo_pt:.2f} 0 0 {logo_pt:.2f} {logo_x:.2f} {logo_y:.2f} cm /Im1 Do Q")
    text_x = (logo_x + logo_pt + 16) if jpeg else 48.0
    text(text_x, 752, biz, size=16, bold=True)
    y_info = 732
    for line in (owner, biz_email, biz_phone, website):
        if line:
            text(text_x, y_info, line, size=9, color="2a3a48")
            y_info -= 12
    text_right(564, 752, "INVOICE", size=22, bold=True, color="0d6b66")
    text_right(564, 732, number, size=12, bold=True)
    text_right(564, 716, status, size=8, bold=True, color="1a8a84")
    hline(40, 664, 532, "e2e8ee", 1.0)

    # BILL TO — FROM is the letterhead (logo + business name)
    y_to = 640
    text(48, y_to, "BILL TO", size=8, bold=True, color="667888")
    y_to -= 16
    text(48, y_to, client, size=12, bold=True)
    y_to -= 14
    if company:
        text(48, y_to, company, size=9, color="2a3a48")
        y_to -= 12
    for line in (client_email, client_phone):
        if line:
            text(48, y_to, line, size=9, color="2a3a48")
            y_to -= 12

    # Meta strip
    fill_rect(48, 538, 516, 52, "f6f8fa")
    text(64, 572, "Issued", size=8, bold=True, color="667888")
    text(64, 554, issue or "—", size=11, bold=True)
    text(220, 572, "Due", size=8, bold=True, color="667888")
    text(220, 554, due or "—", size=11, bold=True)
    text(360, 572, "Currency", size=8, bold=True, color="667888")
    text(360, 554, currency, size=11, bold=True)
    text_right(548, 572, "Amount due", size=8, bold=True, color="667888")
    text_right(548, 552, due_amt, size=13, bold=True, color="0d6b66")

    # Line table
    fill_rect(48, 500, 516, 22, "0d6b66")
    text(64, 507, "Description", size=9, bold=True, color="ffffff")
    text_right(548, 507, "Amount", size=9, bold=True, color="ffffff")
    fill_rect(48, 468, 516, 32, "ffffff")
    hline(48, 468, 516, "e2e8ee")
    text(64, 480, title, size=10)
    text_right(548, 480, amount, size=10, bold=True)

    # Totals
    fill_rect(332, 378, 232, 78, "f6f8fa")
    text(348, 432, "Subtotal", size=9, color="667888")
    text_right(548, 432, amount, size=9)
    text(348, 414, "Paid", size=9, color="667888")
    text_right(548, 414, paid, size=9)
    hline(348, 404, 200, "cfd8e1")
    text(348, 388, "Amount due", size=11, bold=True, color="0d6b66")
    text_right(548, 388, due_amt, size=12, bold=True, color="0d6b66")

    if notes:
        text(48, 432, "Notes", size=8, bold=True, color="667888")
        wrapped = _wrap(notes, 42)
        ny = 416
        for line in wrapped[:6]:
            text(48, ny, line, size=9, color="2a3a48")
            ny -= 12

    fill_rect(0, 0, W, 48, "101920")
    text(48, 22, "InPmnt  ·  Professional invoices & reminders", size=8, color="8a99a8")
    text_right(564, 22, "Thank you for your business", size=8, color="5ee0d8")

    content = "\n".join(ops) + "\n"
    content_b = content.encode("latin-1", "replace")

    objects: list[bytes] = []

    def add(obj: bytes) -> int:
        objects.append(obj)
        return len(objects)

    add(b"<< /Type /Catalog /Pages 2 0 R >>")
    add(b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>")
    resources = b"<< /Font << /F1 4 0 R /F2 5 0 R >>"
    img_obj_num = None
    if jpeg:
        img_obj_num = 6
        resources += b" /XObject << /Im1 6 0 R >>"
    resources += b" >>"
    add(
        f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {W:.0f} {H:.0f}] "
        f"/Resources {resources.decode('ascii')} /Contents 7 0 R >>".encode("ascii")
        if jpeg
        else f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {W:.0f} {H:.0f}] "
        f"/Resources {resources.decode('ascii')} /Contents 6 0 R >>".encode("ascii")
    )
    add(b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>")
    add(b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>")
    if jpeg:
        add(
            f"<< /Type /XObject /Subtype /Image /Width {img_w} /Height {img_h} "
            f"/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {len(jpeg)} >>\n"
            f"stream\n".encode("ascii")
            + jpeg
            + b"endstream"
        )
        stream_num = 7
    else:
        stream_num = 6
    add(f"<< /Length {len(content_b)} >>\nstream\n".encode("ascii") + content_b + b"endstream")

    out = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
    offsets = [0]
    for i, obj in enumerate(objects, start=1):
        offsets.append(len(out))
        out += f"{i} 0 obj\n".encode("ascii")
        out += obj
        if not obj.endswith(b"\n"):
            out += b"\n"
        out += b"endobj\n"
    xref = len(out)
    out += f"xref\n0 {len(objects) + 1}\n".encode("ascii")
    out += b"0000000000 65535 f \n"
    for off in offsets[1:]:
        out += f"{off:010d} 00000 n \n".encode("ascii")
    out += (
        f"trailer\n<< /Size {len(objects) + 1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode(
            "ascii"
        )
    )
    return bytes(out)


def _wrap(text: str, width: int) -> list[str]:
    words = text.replace("\n", " ").split()
    lines: list[str] = []
    cur = ""
    for w in words:
        trial = (cur + " " + w).strip()
        if len(trial) <= width:
            cur = trial
        else:
            if cur:
                lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines or [""]

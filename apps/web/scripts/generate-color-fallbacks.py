#!/usr/bin/env python3
"""Lahirkan warna cadangan untuk browser yang belum mengenal oklch().

Tailwind v4 menulis SELURUH warnanya dengan `oklch()`. Browser yang belum
mendukungnya (Chrome di bawah 111, Samsung Internet lama) membuang tiap
deklarasi itu sebagai tidak sah — bukan menggantinya dengan apa pun. Hasilnya
latar jadi transparan dan warna merek hilang sama sekali, sementara tata
letaknya tetap benar: dialog tembus pandang di atas tabel, sidebar tanpa
latar. Persis yang terlihat di tablet klinik.

Skrip ini membaca tiap deklarasi warna yang memakai oklch(), mengubahnya ke
heks sRGB, lalu menulis ulang semuanya di dalam satu blok
`@supports not (color: oklch(0 0 0))`. Browser modern mengabaikan blok itu
seluruhnya; yang lama memakainya. Tidak ada yang berubah bagi yang sudah
benar.

    python3 scripts/generate-color-fallbacks.py

Jalankan ulang setiap kali palet warnanya berubah. Berkas keluarannya
dilahirkan, jangan disunting tangan.
"""
from __future__ import annotations

import math
import re
import sys
from pathlib import Path

AKAR = Path(__file__).resolve().parent.parent
SUMBER = [AKAR / "src" / "styles.css", AKAR / "src" / "accent-themes.css"]
KELUARAN = AKAR / "src" / "color-fallbacks.css"

# ---------------------------------------------------------------- konversi


def _gamma(x: float) -> float:
    return 12.92 * x if x <= 0.0031308 else 1.055 * (x ** (1 / 2.4)) - 0.055


def oklch_to_rgb(L: float, C: float, H: float) -> tuple[int, int, int]:
    h = math.radians(H)
    a, b = C * math.cos(h), C * math.sin(h)

    l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3
    m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3
    s = (L - 0.0894841775 * a - 1.2914855480 * b) ** 3

    kanal = (
        +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
    )
    # Dipotong ke gamut sRGB: warna di luar gamut mustahil ditampilkan browser
    # lama, dan tepi gamut jauh lebih dekat ke maksudnya daripada nilai negatif.
    return tuple(max(0, min(255, round(_gamma(max(0.0, min(1.0, v))) * 255))) for v in kanal)


_ANGKA = r"(-?[\d.]+%?|none)"
OKLCH = re.compile(rf"oklch\(\s*{_ANGKA}\s+{_ANGKA}\s+{_ANGKA}\s*(?:/\s*{_ANGKA}\s*)?\)", re.I)


def _nilai(tok: str | None, skala: float = 1.0) -> float:
    if tok is None or tok.lower() == "none":
        return 0.0
    return float(tok[:-1]) / 100 * skala if tok.endswith("%") else float(tok)


def ganti_oklch(nilai: str) -> str:
    def repl(m: re.Match[str]) -> str:
        r, g, b = oklch_to_rgb(_nilai(m.group(1)), _nilai(m.group(2), 0.4), _nilai(m.group(3)))
        alpha = m.group(4)

        if alpha is None or alpha.lower() == "none":
            return "#%02x%02x%02x" % (r, g, b)

        a = max(0.0, min(1.0, _nilai(alpha)))
        return "rgba(%d, %d, %d, %s)" % (r, g, b, ("%.4f" % a).rstrip("0").rstrip("."))

    return OKLCH.sub(repl, nilai)


# ------------------------------------------------------------------ parser


def kumpulkan(css: str) -> list[tuple[tuple[str, ...], list[str]]]:
    """Deklarasi ber-oklch beserta rantai selector pembungkusnya.

    Pemindai kurung sederhana, cukup untuk berkas ini: tidak ada kurung di
    dalam string maupun komentar yang perlu dibedakan.
    """
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)

    hasil: list[tuple[tuple[str, ...], list[str]]] = []
    tumpukan: list[str] = []
    sangga = ""

    for ch in css:
        if ch == "{":
            tumpukan.append(sangga.strip())
            sangga = ""
        elif ch == "}":
            if tumpukan:
                tumpukan.pop()
            sangga = ""
        elif ch == ";":
            dekl = sangga.strip()
            if "oklch(" in dekl.lower():
                hasil.append((tuple(tumpukan), [dekl]))
            sangga = ""
        else:
            sangga += ch

    return hasil


def lewati(rantai: tuple[str, ...]) -> bool:
    """Blok yang tidak boleh ikut dilahirkan cadangannya.

    `@supports` yang sudah menguji oklch mengurus dirinya sendiri, dan
    `@theme` bukan CSS biasa — Tailwind mengolahnya saat build.
    """
    return any(
        s.startswith("@supports") and "oklch" in s or s.startswith("@theme")
        for s in rantai
    )


def susun(entri: list[tuple[tuple[str, ...], list[str]]]) -> str:
    """Satukan deklarasi per rantai selector, pertahankan urutan kemunculan."""
    urutan: list[tuple[str, ...]] = []
    per_rantai: dict[tuple[str, ...], list[str]] = {}

    for rantai, dekl in entri:
        if lewati(rantai):
            continue
        if rantai not in per_rantai:
            per_rantai[rantai] = []
            urutan.append(rantai)
        per_rantai[rantai].extend(ganti_oklch(d) + ";" for d in dekl)

    baris: list[str] = []
    for rantai in urutan:
        indent = "  "
        for bagian in rantai:
            baris.append(f"{indent}{bagian} {{")
            indent += "  "
        for d in per_rantai[rantai]:
            baris.append(f"{indent}{d}")
        for i in range(len(rantai) - 1, -1, -1):
            baris.append("  " + "  " * i + "}")
        baris.append("")

    return "\n".join(baris).rstrip()


KEPALA = """/*
 * DILAHIRKAN OLEH scripts/generate-color-fallbacks.py — JANGAN DISUNTING TANGAN.
 *
 * Tailwind v4 menulis seluruh warnanya dengan `oklch()`. Browser yang belum
 * mengenalnya membuang deklarasi itu sebagai tidak sah — bukan menggantinya
 * dengan apa pun — sehingga latar jadi transparan dan warna merek hilang,
 * sementara tata letaknya tetap benar. Di tablet klinik hasilnya: dialog
 * tembus pandang menumpuk di atas tabel, dan sidebar tanpa latar.
 *
 * Blok di bawah menulis ulang tiap warna itu dalam heks sRGB. Browser modern
 * melewatinya seluruhnya, jadi tidak ada yang berubah bagi mereka.
 *
 * Perbarui dengan: python3 scripts/generate-color-fallbacks.py
 */
@supports not (color: oklch(0 0 0)) {
"""


def main() -> int:
    entri: list[tuple[tuple[str, ...], list[str]]] = []
    for berkas in SUMBER:
        entri.extend(kumpulkan(berkas.read_text()))

    isi = susun(entri)
    if not isi:
        print("Tidak ada warna oklch yang ditemukan.", file=sys.stderr)
        return 1

    KELUARAN.write_text(KEPALA + isi + "\n}\n")

    jumlah = isi.count(";")
    print(f"{KELUARAN.relative_to(AKAR)}: {jumlah} warna cadangan ditulis.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

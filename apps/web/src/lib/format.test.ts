import { describe, expect, it } from "vitest"

import {
  formatCurrency,
  formatCurrencyCompact,
  formatDate,
  formatDateTime,
} from "./format.ts"

describe("formatCurrency", () => {
  it("menulis rupiah tanpa desimal", () => {
    expect(formatCurrency(250000)).toContain("250.000")
    expect(formatCurrency(250000)).not.toContain(",00")
  })

  it("angka tidak valid dianggap nol, bukan NaN di layar", () => {
    expect(formatCurrency(Number.NaN)).toContain("0")
    expect(formatCurrency(Number.POSITIVE_INFINITY)).toContain("0")
  })
})

describe("formatDate", () => {
  it("menulis tanggal saja", () => {
    const result = formatDate("2026-08-14T09:30:00+07:00")

    expect(result).toMatch(/2026/)
    expect(result).not.toMatch(/\d{2}[.:]\d{2}/)
  })
})

describe("formatDateTime", () => {
  it("menulis tanggal beserta jam", () => {
    expect(formatDateTime("2026-08-14T09:30:00Z")).toMatch(/2026/)
  })

  it("nilai kosong atau rusak jadi strip, bukan Invalid Date", () => {
    expect(formatDateTime(null)).toBe("-")
    expect(formatDateTime(undefined)).toBe("-")
    expect(formatDateTime("")).toBe("-")
    expect(formatDateTime("bukan tanggal")).toBe("-")
    expect(formatDate(null)).toBe("-")
  })
})

/**
 * Nominal yang dipendekkan untuk kartu sempit.
 *
 * Lahir dari kartu KPI selebar 140px — yang terjadi saat lima kartu berbagi
 * area ~700px di 1024px dengan sidebar terbuka. "Rp 12.450.000" membungkus
 * jadi dua baris di sana, tingginya meloncat, dan sebaris kartu tidak lagi
 * rata.
 */
describe("formatCurrencyCompact", () => {
  it("memendekkan juta dengan satu angka di belakang koma", () => {
    expect(formatCurrencyCompact(1_234_567)).toBe("Rp1,2 jt")
    expect(formatCurrencyCompact(12_450_000)).toBe("Rp12,5 jt")
  })

  /** Bulat tetap bulat: "Rp12,0 jt" membuang ruang untuk nol yang kosong. */
  it("tidak menambahkan desimal pada angka bulat", () => {
    expect(formatCurrencyCompact(12_000_000)).toBe("Rp12 jt")
    expect(formatCurrencyCompact(3_000)).toBe("Rp3 rb")
  })

  it("memakai satuan Indonesia sampai triliun", () => {
    expect(formatCurrencyCompact(950_000)).toBe("Rp950 rb")
    expect(formatCurrencyCompact(2_500_000_000)).toBe("Rp2,5 M")
    expect(formatCurrencyCompact(1_800_000_000_000)).toBe("Rp1,8 T")
  })

  /** Di bawah seribu tidak ada yang bisa dipendekkan. */
  it("membiarkan nominal yang sudah pendek apa adanya", () => {
    expect(formatCurrencyCompact(750)).toBe(formatCurrency(750))
    expect(formatCurrencyCompact(0)).toBe(formatCurrency(0))
  })

  it("mempertahankan tanda minus", () => {
    expect(formatCurrencyCompact(-1_200_000)).toBe("-Rp1,2 jt")
  })

  /** Nilai rusak jadi nol, bukan NaN - sama seperti formatCurrency. */
  it("tidak pernah menulis NaN", () => {
    expect(formatCurrencyCompact(Number.NaN)).toBe(formatCurrency(0))
    expect(formatCurrencyCompact(Number.POSITIVE_INFINITY)).toBe(formatCurrency(0))
  })
})

import { describe, expect, it } from "vitest"

import { formatCurrency, formatDate, formatDateTime } from "./format.ts"

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

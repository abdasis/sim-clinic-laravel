import { describe, expect, it } from "vitest"

import {
  computeRange,
  periodLabel,
  shiftPeriod,
} from "./schedule-range.ts"

/**
 * Rentang tanggal jadwal — perhitungan yang paling mudah salah diam-diam:
 * kalau rentangnya meleset sehari, bookingnya hilang dari layar tanpa ada
 * pesan galat apa pun.
 */
describe("computeRange", () => {
  it("mengambil satu hari saja untuk tampilan harian", () => {
    expect(computeRange("2026-08-22", "day")).toEqual({
      from: "2026-08-22",
      to: "2026-08-22",
    })
  })

  it("memulai minggu dari Senin, bukan Minggu", () => {
    // 22 Agustus 2026 jatuh pada Sabtu.
    expect(computeRange("2026-08-22", "week")).toEqual({
      from: "2026-08-17",
      to: "2026-08-23",
    })
  })

  /**
   * Inti tampilan bulanan: kotak kalender selalu utuh Senin-Minggu, jadi hari
   * titipan dari bulan tetangga ikut diminta. Tanpa ini baris pertama dan
   * terakhir terlihat kosong padahal ada bookingnya.
   */
  it("melebarkan bulan sampai batas minggu penuh", () => {
    // Agustus 2026 mulai Sabtu dan berakhir Senin.
    expect(computeRange("2026-08-22", "month")).toEqual({
      from: "2026-07-27",
      to: "2026-09-06",
    })
  })

  it("tetap utuh untuk bulan yang persis mulai Senin", () => {
    // Juni 2026 mulai Senin.
    expect(computeRange("2026-06-15", "month").from).toBe("2026-06-01")
  })

  it("tidak terpengaruh tanggal mana pun di bulan yang sama", () => {
    expect(computeRange("2026-08-01", "month")).toEqual(
      computeRange("2026-08-31", "month"),
    )
  })
})

describe("shiftPeriod", () => {
  it("melangkah sehari pada tampilan harian", () => {
    expect(shiftPeriod("2026-08-22", "day", 1)).toBe("2026-08-23")
    expect(shiftPeriod("2026-08-22", "day", -1)).toBe("2026-08-21")
  })

  it("melangkah sepekan pada tampilan mingguan", () => {
    expect(shiftPeriod("2026-08-22", "week", 1)).toBe("2026-08-29")
  })

  it("melangkah sebulan pada tampilan bulanan", () => {
    expect(shiftPeriod("2026-08-22", "month", 1)).toBe("2026-09-22")
  })

  /** Bulan pendek tidak boleh melompati satu bulan penuh. */
  it("menjatuhkan 31 Januari ke akhir Februari, bukan ke Maret", () => {
    expect(shiftPeriod("2026-01-31", "month", 1)).toBe("2026-02-28")
  })

  it("menyeberang tahun tanpa tersesat", () => {
    expect(shiftPeriod("2026-12-15", "month", 1)).toBe("2027-01-15")
    expect(shiftPeriod("2026-01-05", "week", -1)).toBe("2025-12-29")
  })
})

describe("periodLabel", () => {
  it("menyebut bulan dan tahun pada tampilan bulanan", () => {
    expect(periodLabel("2026-08-22", "month")).toContain("Agustus")
    expect(periodLabel("2026-08-22", "month")).toContain("2026")
  })

  it("menyebut rentang minggunya, bukan satu tanggal", () => {
    const label = periodLabel("2026-08-22", "week")

    expect(label).toContain("17")
    expect(label).toContain("23")
  })

  it("menyebut hari dan tanggal lengkap pada tampilan harian", () => {
    const label = periodLabel("2026-08-22", "day")

    expect(label).toContain("Sabtu")
    expect(label).toContain("Agustus")
  })
})

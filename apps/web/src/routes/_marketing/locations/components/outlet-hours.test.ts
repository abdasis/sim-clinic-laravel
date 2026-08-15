import { describe, expect, it } from "vitest"

import { isOpenAt, parseRange, todayIndex } from "./opening-hours.ts"
import type { OpeningHour } from "#/lib/clinic-outlet.ts"

const HOURS: OpeningHour[] = [
  { day: { id: "Senin" }, hours: "09.00–20.00" },
  { day: { id: "Selasa" }, hours: "09.00–20.00" },
  { day: { id: "Rabu" }, hours: "09.00–20.00" },
  { day: { id: "Kamis" }, hours: "09.00–20.00" },
  { day: { id: "Jumat" }, hours: "09.00–20.00" },
  { day: { id: "Sabtu" }, hours: "10.00–18.00" },
  { day: { id: "Minggu" }, hours: null },
]

/** 2026-08-17 adalah hari Senin; tanggal setelahnya maju satu hari. */
function at(dayOffset: number, hour: number, minute = 0): Date {
  return new Date(2026, 7, 17 + dayOffset, hour, minute)
}

describe("parseRange", () => {
  it("membaca format titik dan titik dua", () => {
    expect(parseRange("09.00–20.00")).toEqual({ open: 540, close: 1200 })
    expect(parseRange("09:00 - 20:00")).toEqual({ open: 540, close: 1200 })
  })

  it("mengembalikan null untuk teks yang tidak dikenal", () => {
    expect(parseRange("dengan perjanjian")).toBeNull()
    expect(parseRange("")).toBeNull()
  })
})

describe("todayIndex", () => {
  it("menaruh Senin di awal, bukan Minggu", () => {
    // getDay() memberi Minggu = 0; daftar jam dimulai dari Senin.
    expect(todayIndex(at(0, 12))).toBe(0)
    expect(todayIndex(at(5, 12))).toBe(5)
    expect(todayIndex(at(6, 12))).toBe(6)
  })
})

describe("isOpenAt", () => {
  it("buka di tengah jam kerja", () => {
    expect(isOpenAt(HOURS, at(0, 12))).toBe(true)
  })

  it("tutup sebelum buka dan setelah tutup", () => {
    expect(isOpenAt(HOURS, at(0, 8, 59))).toBe(false)
    expect(isOpenAt(HOURS, at(0, 20, 0))).toBe(false)
    expect(isOpenAt(HOURS, at(0, 19, 59))).toBe(true)
  })

  it("mengikuti jam Sabtu yang berbeda", () => {
    expect(isOpenAt(HOURS, at(5, 18, 30))).toBe(false)
    expect(isOpenAt(HOURS, at(5, 17, 30))).toBe(true)
  })

  it("hari libur selalu tutup", () => {
    expect(isOpenAt(HOURS, at(6, 12))).toBe(false)
  })
})

import { describe, expect, it } from "vitest"

import { overlapWhen } from "./overlap-warning.ts"

/**
 * Peringatan bentrok muncul sebagai toast tepat setelah booking tersimpan —
 * dibaca sekilas, sekali. Sebelumnya isinya cap waktu ISO8601 utuh, lengkap
 * dengan huruf T dan zona waktunya, jadi staf tidak bisa langsung tahu jam
 * berapa bentroknya.
 */
describe("overlapWhen", () => {
  it("menulis tanggal dan rentang jam, bukan cap waktu ISO", () => {
    const text = overlapWhen({
      start_at: "2026-08-22T10:00:00+07:00",
      end_at: "2026-08-22T11:30:00+07:00",
    })

    expect(text).not.toContain("T10:00:00")
    expect(text).not.toContain("+07:00")
    expect(text).toContain("Agu")
    expect(text).toContain("-")
  })

  /** Tanggalnya cukup sekali: bentrok selalu terjadi di hari yang sama. */
  it("tidak mengulang tanggal di ujung rentangnya", () => {
    const text = overlapWhen({
      start_at: "2026-08-22T10:00:00+07:00",
      end_at: "2026-08-22T11:30:00+07:00",
    })

    expect(text.split("Agu").length - 1).toBe(1)
  })

  /** Data rusak menghasilkan tanda hubung, bukan "Invalid Date" di toast. */
  it("menahan cap waktu yang tidak terbaca", () => {
    expect(overlapWhen({ start_at: "entah", end_at: "entah" })).toBe("-")
    expect(
      overlapWhen({ start_at: "2026-08-22T10:00:00+07:00", end_at: "entah" }),
    ).not.toContain("Invalid")
  })
})

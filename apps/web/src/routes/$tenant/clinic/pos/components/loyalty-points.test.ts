import { describe, expect, it } from "vitest"

import { capToBill, loyaltyPointsPreview, redeemValue } from "./loyalty-points.ts"

describe("loyaltyPointsPreview", () => {
  it("membulatkan ke bawah, bukan ke angka terdekat", () => {
    expect(loyaltyPointsPreview(19_000)).toBe(1)
    expect(loyaltyPointsPreview(10_000)).toBe(1)
    expect(loyaltyPointsPreview(9_999)).toBe(0)
    expect(loyaltyPointsPreview(105_000)).toBe(10)
  })

  it("tidak pernah negatif", () => {
    expect(loyaltyPointsPreview(-50_000)).toBe(0)
    expect(loyaltyPointsPreview(0)).toBe(0)
  })
})

describe("redeemValue", () => {
  /** Angkanya harus sama persis dengan App\Support\LoyaltyPoints di server —
   * kasir menyebutkannya ke pasien sebelum notanya terbit. */
  it("menilai tiap poin seribu rupiah", () => {
    expect(redeemValue(30)).toBe(30_000)
    expect(redeemValue(1)).toBe(1_000)
    expect(redeemValue(0)).toBe(0)
  })

  it("tidak pernah negatif", () => {
    expect(redeemValue(-10)).toBe(0)
  })
})

/**
 * Tarif milik tiap klinik, jadi yang dijaga bukan angkanya melainkan bahwa
 * angka yang dikirim benar-benar dipakai — bukan bawaan yang diam-diam menang.
 */
describe("tarif klinik", () => {
  const rates = { earn_rate: 50_000, redeem_rate: 500, min_redeem: 5 }

  it("memakai tarif dapat poin milik klinik", () => {
    expect(loyaltyPointsPreview(200_000, rates)).toBe(4)
    expect(loyaltyPointsPreview(200_000)).toBe(20)
  })

  it("memakai tarif tukar milik klinik", () => {
    expect(redeemValue(20, rates)).toBe(10_000)
    expect(redeemValue(20)).toBe(20_000)
  })

  it("memangkas penukaran menurut tarif tukar klinik", () => {
    // Rp200.000 menampung 400 poin saat satu poin bernilai Rp500.
    expect(capToBill(500, 200_000, rates)).toBe(400)
    expect(capToBill(500, 200_000)).toBe(200)
  })

  /** Tarif rusak tidak boleh melahirkan Infinity di layar kasir. */
  it("jatuh ke bawaan saat tarifnya nol atau bukan angka", () => {
    const broken = { earn_rate: 0, redeem_rate: Number.NaN, min_redeem: 10 }

    expect(loyaltyPointsPreview(200_000, broken)).toBe(20)
    expect(redeemValue(20, broken)).toBe(20_000)
  })
})

describe("capToBill", () => {
  /**
   * Poin memotong yang harus dibayar; ia bukan uang yang bisa diambil pulang
   * sebagai kembalian. Kelebihannya dipangkas, bukan ditolak.
   */
  it("memangkas penukaran yang melebihi tagihan", () => {
    expect(capToBill(500, 200_000)).toBe(200)
    expect(capToBill(30, 200_000)).toBe(30)
  })

  it("tidak menyisakan penukaran saat tagihannya nol", () => {
    expect(capToBill(50, 0)).toBe(0)
  })

  /** Tagihan Rp19.500 cuma menampung 19 poin — sisanya tidak ada yang dipotong. */
  it("membulatkan ke bawah mengikuti tagihannya", () => {
    expect(capToBill(50, 19_500)).toBe(19)
  })
})

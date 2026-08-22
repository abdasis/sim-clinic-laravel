import { describe, expect, it } from "vitest"

import { productLines, treatmentLines } from "./record-lines.ts"
import type { RecordRow, TransactionItemRow } from "./record-types.ts"

function billed(
  name: string,
  overrides: Partial<TransactionItemRow> = {},
): TransactionItemRow {
  return {
    name,
    kind: "service",
    unit_price: 300000,
    qty: 1,
    subtotal: 300000,
    ...overrides,
  }
}

function record(
  treatments: string[],
  items: TransactionItemRow[] = [],
): RecordRow {
  return {
    id: 1,
    treatments: treatments.map((service_name, id) => ({
      id,
      service_name,
      notes: null,
    })),
    transaction:
      items.length > 0
        ? { id: 1, performers: [], items }
        : null,
  }
}

/**
 * Satu kunjungan tercatat di dua tempat: dokter menulis tindakannya di rekam
 * medis, kasir menagihnya di POS. Keduanya menyebut layanan yang sama, jadi
 * kolom Tindakan tidak boleh menampilkannya dua kali.
 */
describe("treatmentLines", () => {
  /** Inti pertanyaannya: booking facial, dicatat dokter, ditagih kasir. */
  it("menggabungkan tindakan yang dicatat dan ditagih jadi satu baris", () => {
    const lines = treatmentLines(record(["Facial Glow"], [billed("Facial Glow")]))

    expect(lines).toHaveLength(1)
    expect(lines[0].label).toBe("Facial Glow")
    expect(lines[0].amount).toBe(300000)
  })

  /** Tindakan yang dicatat tapi belum ditagih tetap terbaca, tanpa harga. */
  it("menampilkan tindakan yang belum ditagih", () => {
    const lines = treatmentLines(record(["Totok Wajah"]))

    expect(lines).toHaveLength(1)
    expect(lines[0].amount).toBeUndefined()
  })

  /** Tagihan tanpa catatan klinis juga tidak boleh hilang. */
  it("menampilkan tagihan yang belum dicatat dokter", () => {
    const lines = treatmentLines(record([], [billed("Peeling")]))

    expect(lines).toHaveLength(1)
    expect(lines[0].label).toBe("Peeling")
    expect(lines[0].amount).toBe(300000)
  })

  /** Yang berpasangan digabung, yang tidak tetap berdiri sendiri. */
  it("menggabungkan yang berpasangan tanpa membuang sisanya", () => {
    const lines = treatmentLines(
      record(
        ["Facial Glow", "Totok Wajah"],
        [billed("Facial Glow"), billed("Peeling")],
      ),
    )

    expect(lines.map((line) => line.label)).toEqual([
      "Facial Glow",
      "Totok Wajah",
      "Peeling",
    ])
    expect(lines[0].amount).toBe(300000)
    expect(lines[1].amount).toBeUndefined()
  })

  /**
   * Dua sesi layanan yang sama dalam sehari: keduanya berhak atas harganya
   * sendiri. Penandaan per nama membuat sesi kedua kehilangan harga.
   */
  it("memasangkan dua sesi layanan yang sama satu per satu", () => {
    const lines = treatmentLines(
      record(
        ["Facial Glow", "Facial Glow"],
        [billed("Facial Glow"), billed("Facial Glow", { subtotal: 250000 })],
      ),
    )

    expect(lines).toHaveLength(2)
    expect(lines[0].amount).toBe(300000)
    expect(lines[1].amount).toBe(250000)
  })

  /** Dua catatan, satu tagihan: yang kedua tetap muncul tanpa harga. */
  it("tidak mengarang harga untuk sesi yang tidak ditagih", () => {
    const lines = treatmentLines(
      record(["Facial Glow", "Facial Glow"], [billed("Facial Glow")]),
    )

    expect(lines).toHaveLength(2)
    expect(lines[0].amount).toBe(300000)
    expect(lines[1].amount).toBeUndefined()
  })

  /** Beda huruf besar-kecil bukan alasan jadi baris kembar. */
  it("menyamakan nama yang hanya beda besar-kecil huruf", () => {
    const lines = treatmentLines(
      record(["Facial Glow"], [billed("FACIAL GLOW")]),
    )

    expect(lines).toHaveLength(1)
    expect(lines[0].amount).toBe(300000)
  })

  /** Spasi berlebih dari salin tempel juga tidak boleh memecah barisnya. */
  it("menyamakan nama yang hanya beda spasi", () => {
    const lines = treatmentLines(
      record(["Facial  Glow "], [billed("Facial Glow")]),
    )

    expect(lines).toHaveLength(1)
  })

  /** Layanan yang memang berbeda tetap dua baris. */
  it("tidak menggabungkan layanan yang namanya memang berbeda", () => {
    const lines = treatmentLines(
      record(["Facial Glow"], [billed("Facial Premium")]),
    )

    expect(lines).toHaveLength(2)
  })

  /** Produk tidak ikut masuk kolom tindakan. */
  it("tidak menarik produk ke kolom tindakan", () => {
    const lines = treatmentLines(
      record(["Facial Glow"], [billed("Serum", { kind: "product" })]),
    )

    expect(lines).toHaveLength(1)
    expect(lines[0].amount).toBeUndefined()
  })

  it("aman saat kunjungannya belum punya catatan maupun nota", () => {
    expect(treatmentLines(record([]))).toEqual([])
  })
})

describe("productLines", () => {
  it("hanya mengambil produk, bukan tindakan", () => {
    const lines = productLines(
      record(
        [],
        [billed("Facial Glow"), billed("Serum", { kind: "product" })],
      ),
    )

    expect(lines).toHaveLength(1)
    expect(lines[0].label).toBe("Serum")
  })

  it("menyebut jumlah hanya bila lebih dari satu", () => {
    const lines = productLines(
      record(
        [],
        [
          billed("Serum", { kind: "product", qty: 2 }),
          billed("Toner", { kind: "product", qty: 1 }),
        ],
      ),
    )

    expect(lines[0].qty).toBe(2)
    expect(lines[1].qty).toBeUndefined()
  })
})

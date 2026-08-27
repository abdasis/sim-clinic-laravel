import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import {
  DiscountField,
  discountAmount,
  EMPTY_DISCOUNT,
} from "./discount-field.tsx"
import type { DiscountState } from "./discount-field.tsx"

setTranslations({
  pos: {
    discount: "Potongan",
    discount_type: "Jenis Potongan",
    discount_value: "Besar Potongan",
    discount_none: "Tanpa potongan",
    discount_hint_percent: "Boleh pecahan, mis. 70,5 untuk 70,5%.",
  },
  clinic: {
    discount_type: { percent: "Persen (%)", fixed: "Nominal (Rp)" },
  },
})

const state = (kind: DiscountState["kind"], value: string): DiscountState => ({
  kind,
  value,
})

/**
 * Potongan di tingkat nota. Angkanya dihitung dua kali — di layar kasir dan
 * di server — jadi keduanya harus berhenti di tempat yang sama, termasuk
 * saat potongannya melebihi tagihan.
 */
describe("discountAmount", () => {
  it("tidak memotong apa pun tanpa pilihan", () => {
    expect(discountAmount(EMPTY_DISCOUNT, 200000)).toBe(0)
  })

  it("menghitung persen", () => {
    expect(discountAmount(state("percent", "10"), 200000)).toBe(20000)
  })

  /** Inti permintaannya: 70,5% harus terhitung, bukan dibulatkan jadi 70. */
  it("menghitung persen pecahan", () => {
    expect(discountAmount(state("percent", "70.5"), 200000)).toBe(141000)
  })

  /** Kasir Indonesia mengetik koma, bukan titik. */
  it("menerima koma sebagai pemisah desimal", () => {
    expect(discountAmount(state("percent", "70,5"), 200000)).toBe(141000)
  })

  it("menghitung potongan nominal", () => {
    expect(discountAmount(state("fixed", "25000"), 200000)).toBe(25000)
  })

  /**
   * Berhenti di gratis, tidak pernah melahirkan nota bernilai minus — sama
   * seperti perlakuan di server, supaya angka di layar dan angka yang
   * tersimpan tidak pernah berbeda.
   */
  it("berhenti di seluruh tagihan saat potongannya kelewat besar", () => {
    expect(discountAmount(state("fixed", "999000"), 200000)).toBe(200000)
    expect(discountAmount(state("percent", "150"), 200000)).toBe(200000)
  })

  it("mengabaikan isian yang bukan angka", () => {
    expect(discountAmount(state("percent", "entah"), 200000)).toBe(0)
    expect(discountAmount(state("fixed", ""), 200000)).toBe(0)
  })

  it("tidak menerima potongan nol atau negatif", () => {
    expect(discountAmount(state("percent", "0"), 200000)).toBe(0)
    expect(discountAmount(state("fixed", "-5000"), 200000)).toBe(0)
  })
})

function renderField(value: DiscountState, onChange = vi.fn()) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <DiscountField value={value} onChange={onChange} total={200000} />
    </QueryClientProvider>,
  )

  return onChange
}

describe("DiscountField", () => {
  it("menyembunyikan kolom angka saat tidak ada potongan", () => {
    renderField(EMPTY_DISCOUNT)

    expect(screen.queryByLabelText("Besar Potongan")).toBeNull()
  })

  it("menampilkan hasil potongannya dalam Rupiah", () => {
    const { container } = render(
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <DiscountField
          value={state("percent", "10")}
          onChange={() => {}}
          total={200000}
        />
      </QueryClientProvider>,
    )

    expect(container.textContent).toContain("20.000")
  })

  /**
   * 10 sebagai persen dan 10 sebagai rupiah adalah dua hal yang jauh
   * berbeda; membawanya menyeberang saat jenisnya berganti mengundang salah
   * tekan yang baru ketahuan setelah nota tercetak.
   */
  it("mengosongkan nilainya saat jenis potongannya berganti", () => {
    const onChange = renderField(state("percent", "70,5"))

    fireEvent.change(screen.getByLabelText("Jenis Potongan"), {
      target: { value: "fixed" },
    })

    expect(onChange).toHaveBeenCalledWith({ kind: "fixed", value: "" })
  })
})

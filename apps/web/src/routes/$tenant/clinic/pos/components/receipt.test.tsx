import { describe, expect, it } from "vitest"
import { render } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { Receipt, type ReceiptData } from "./receipt.tsx"

setTranslations({
  invoice: {
    receipt: "NOTA / RECEIPT",
    group_service: "Layanan / Tindakan",
    group_product: "Produk",
    item_total: "Subtotal Item",
    qty: "Jumlah",
    qty_short: "Jml",
    description: "Deskripsi",
    subtotal: "Subtotal",
    grand_total: "TOTAL",
    outstanding: "Sisa Bayar",
    title: "Invoice",
  },
})

function renderReceipt(data: ReceiptData, clinic?: Parameters<typeof Receipt>[0]["clinic"]) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, enabled: false } },
  })

  return render(
    <QueryClientProvider client={client}>
      <Receipt data={data} clinic={clinic} printedAt="15 Agu 2026, 18.56" />
    </QueryClientProvider>,
  )
}

const base: ReceiptData = {
  invoice_number: "INV-20260815-0001",
  subtotal: "810000.00",
  paid_amount: "0.00",
  outstanding_amount: "810000",
  issued_at: "2026-08-15T11:00:00+00:00",
  patient_name: "Dessy Natalia",
  cashier_name: "Melisa",
  items: [
    { id: 1, name: "Chemical Peeling", kind: "service", unit_price: "350000.00", qty: 1, subtotal: "350000.00" },
    { id: 2, name: "Serum Vitamin C", kind: "product", unit_price: "120000.00", qty: 2, subtotal: "240000.00" },
  ],
}

/**
 * Nota adalah dokumen yang dipegang pasien, jadi yang diuji di sini bukan
 * gayanya melainkan isinya: pengelompokan baris, angka yang dijumlahkan, dan
 * bagian yang harus menghilang rapi saat datanya belum ada.
 */
describe("Receipt", () => {
  it("memisahkan layanan dan produk ke kelompoknya masing-masing", () => {
    const { getByText } = renderReceipt(base)

    expect(getByText("Layanan / Tindakan")).toBeTruthy()
    expect(getByText("Produk")).toBeTruthy()
    expect(getByText("Chemical Peeling")).toBeTruthy()
    expect(getByText("Serum Vitamin C")).toBeTruthy()
  })

  it("menampilkan angka tanpa Rp karena mata uangnya sudah di kepala kolom", () => {
    const { getAllByText, container } = renderReceipt(base)

    expect(getAllByText("810.000").length).toBeGreaterThan(0)
    expect(container.textContent).not.toContain("Rp")
  })

  it("menyembunyikan kelompok yang kosong", () => {
    const { queryByText } = renderReceipt({
      ...base,
      items: [base.items[0]],
    })

    expect(queryByText("Layanan / Tindakan")).toBeTruthy()
    expect(queryByText("Produk")).toBeNull()
  })

  it("tetap utuh saat identitas klinik belum diisi", () => {
    const { container, queryByRole } = renderReceipt(base, {
      name: null,
      address: null,
      phone: null,
      logo_url: null,
      tagline: null,
      receipt_note: null,
    })

    // Tidak ada logo pecah dan tidak ada baris alamat kosong yang menganga.
    expect(queryByRole("img")).toBeNull()
    expect(container.textContent).toContain("NOTA / RECEIPT")
  })

  it("memakai kepala kolom jumlah yang pendek", () => {
    // "Jumlah" utuh tidak muat di kolom selebar dua digit pada kertas 48mm:
    // di sana kata itu terpenggal jadi tumpukan huruf atau menabrak kolom
    // sebelahnya. Kepala kolomnya harus tetap singkat.
    const { getByText, queryByText } = renderReceipt(base)

    expect(getByText("Jml")).toBeTruthy()
    expect(queryByText("Jumlah")).toBeNull()
  })

  it("menyebut totalnya subtotal item, bukan cacah item", () => {
    const { getByText } = renderReceipt(base)

    expect(getByText("Subtotal Item")).toBeTruthy()
  })

  it("menyembunyikan sisa bayar ketika tagihan sudah lunas", () => {
    const { queryByText } = renderReceipt({
      ...base,
      paid_amount: "810000.00",
      outstanding_amount: "0",
    })

    expect(queryByText("Sisa Bayar")).toBeNull()
  })
})

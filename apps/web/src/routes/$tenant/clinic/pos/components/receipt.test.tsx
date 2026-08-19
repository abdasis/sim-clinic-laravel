import { describe, expect, it } from "vitest"
import { cleanup, render } from "@testing-library/react"
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
    item_count: ":count item",
    paid_amount: "Sudah Dibayar",
    change: "Kembali",
    discount: "Diskon Promo",
    reprint: "Cetak Ulang",
    cancelled: "Transaksi Dibatalkan",
    cancelled_note: "Nota ini tidak berlaku sebagai bukti pembayaran.",
    performers: "Terapis",
    customer: "Pelanggan",
    served_by: "Dilayani",
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

  it("menulis jumlah dan harga satuan di tiap baris, termasuk saat qty 1", () => {
    // Pasien membandingkan nota dengan daftar harga. Harga satuan yang kadang
    // muncul kadang hilang membuat baris yang paling mahal justru paling
    // sulit dipertanggungjawabkan di meja kasir.
    const { getByText } = renderReceipt(base)

    expect(getByText("1 x 350.000")).toBeTruthy()
    expect(getByText("2 x 120.000")).toBeTruthy()
  })

  it("menyebut totalnya subtotal item beserta cacahnya", () => {
    const { getByText } = renderReceipt(base)

    expect(getByText("Subtotal Item (3 item)")).toBeTruthy()
  })

  it("menulis nama panjang utuh tanpa dipotong kolom angka", () => {
    const { getByText } = renderReceipt({
      ...base,
      items: [
        {
          ...base.items[0],
          name: "Chemical Peeling Wajah dan Leher Paket Lengkap",
        },
      ],
    })

    expect(
      getByText("Chemical Peeling Wajah dan Leher Paket Lengkap"),
    ).toBeTruthy()
  })

  it("menampilkan kembalian hanya saat bayarnya melebihi total", () => {
    const payments = [
      { id: 1, method: "cash", method_label: "Tunai", amount: "1000000.00" },
    ]

    const { getByText } = renderReceipt({
      ...base,
      paid_amount: "1000000.00",
      outstanding_amount: "0",
      payments,
    })

    expect(getByText("Kembali")).toBeTruthy()
    expect(getByText("190.000")).toBeTruthy()

    // Kedua render menempel di document.body yang sama, jadi yang pertama
    // harus dibersihkan dulu sebelum menanyakan ketiadaan barisnya.
    cleanup()

    const pas = renderReceipt({
      ...base,
      paid_amount: "810000.00",
      outstanding_amount: "0",
      payments: [{ ...payments[0], amount: "810000.00" }],
    })

    expect(pas.queryByText("Kembali")).toBeNull()
  })

  it("menandai cetakan kedua sebagai cetak ulang", () => {
    const { queryByText } = renderReceipt(base)

    expect(queryByText(/Cetak Ulang/)).toBeNull()

    cleanup()

    const ulang = renderReceipt({ ...base, print_count: 3 })

    expect(ulang.getByText("Cetak Ulang #3")).toBeTruthy()
  })

  it("menyatakan transaksi yang dibatalkan tidak berlaku sebagai bukti bayar", () => {
    const { getByText } = renderReceipt({
      ...base,
      cancelled_at: "2026-08-15T12:00:00+00:00",
    })

    expect(getByText("Transaksi Dibatalkan")).toBeTruthy()
    expect(
      getByText("Nota ini tidak berlaku sebagai bukti pembayaran."),
    ).toBeTruthy()
  })

  it("mencantumkan pelaksana tindakan, bukan cuma kasirnya", () => {
    const { getByText, queryByText } = renderReceipt({
      ...base,
      performers: [
        { id: 1, name: "Sinta" },
        { id: 2, name: "dr. Andi" },
      ],
    })

    expect(getByText("Terapis")).toBeTruthy()
    expect(getByText("Sinta, dr. Andi")).toBeTruthy()
    // Kasirnya tetap tercantum terpisah; pelaksana bukan penggantinya.
    expect(queryByText("Melisa")).toBeTruthy()

    cleanup()

    const tanpa = renderReceipt(base)

    expect(tanpa.queryByText("Terapis")).toBeNull()
  })

  it("menuliskan potongan promo sebagai barisnya sendiri", () => {
    const { getByText } = renderReceipt({
      ...base,
      items: [
        { ...base.items[0], list_price: "400000.00" },
        { ...base.items[1], list_price: "300000.00" },
      ],
    })

    // Normal 400.000 + 2x300.000 = 1.000.000; yang ditagih 810.000.
    expect(getByText("Subtotal Item (3 item)")).toBeTruthy()
    expect(getByText("1.000.000")).toBeTruthy()
    expect(getByText("Diskon Promo")).toBeTruthy()
    expect(getByText("-190.000")).toBeTruthy()
  })

  it("tidak mengarang potongan untuk transaksi lama tanpa harga normal", () => {
    const { queryByText } = renderReceipt(base)

    expect(queryByText("Diskon Promo")).toBeNull()
  })

  it("mengabaikan harga normal yang justru lebih murah dari yang dibayar", () => {
    // Harga katalog bisa turun setelah transaksinya lewat; itu bukan
    // potongan yang pernah diterima pasien.
    const { queryByText } = renderReceipt({
      ...base,
      items: [{ ...base.items[0], list_price: "100000.00" }, base.items[1]],
    })

    expect(queryByText("Diskon Promo")).toBeNull()
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

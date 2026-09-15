import { afterEach, describe, expect, it } from "vitest"
import { cleanup, fireEvent, render, screen } from "@testing-library/react"
import { useForm } from "react-hook-form"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { PosCheckoutPanel, type PatientFormValues } from "./pos-checkout-panel.tsx"
import { EMPTY_DISCOUNT } from "./discount-field.tsx"
import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"

setTranslations({
  general: {
    loading: "Memuat...",
    load_failed: "Data gagal dimuat",
    no_data: "Tidak ada data",
    search: "Cari",
  },
  pos: {
    patient: "Pasien",
    total: "Total",
    empty_cart: "Keranjang kosong",
    empty_cart_desc: "Pilih layanan dulu",
    payment: "Pembayaran",
    method: "Metode",
    amount: "Jumlah",
    paid_amount: "Dibayar",
    outstanding: "Sisa",
    member_active: "Member aktif — potongan otomatis di nota.",
    cart: { title: "Keranjang" },
  },
  commission: { therapist: "Terapis" },
})

const PATIENTS = [
  { label: "Ibu Sinta", value: "1" },
  { label: "Pak Budi", value: "2" },
]

function Harness(
  props: Partial<React.ComponentProps<typeof PosCheckoutPanel>>,
) {
  const form = useForm<PatientFormValues>({
    defaultValues: { patient_id: "", booking_id: "" },
  })

  return (
    <PosCheckoutPanel
      form={form}
      patientOptions={PATIENTS}
      bookingOptions={[]}
      staff={[{ id: 9, name: "Mbak Rara", clinic_role: "therapist" }]}
      discount={EMPTY_DISCOUNT}
      onDiscountChange={() => {}}
      performerIds={[]}
      onPerformersChange={() => {}}
      onOfferedBy={() => {}}
      items={[]}
      total={0}
      onStep={() => {}}
      onRemove={() => {}}
      onClear={() => {}}
      onPaymentChange={() => {}}
      {...props}
    />
  )
}

function renderPanel(ui: React.ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  // TooltipProvider dipasang di root aplikasi; di sini disediakan sendiri
  // supaya baris keranjang (yang memakai Tooltip pada tombolnya) berdiri
  // seperti saat dipakai.
  return render(
    <QueryClientProvider client={client}>
      <TooltipProvider>{ui}</TooltipProvider>
    </QueryClientProvider>,
  )
}

/** Nilai rupiah dicetak sebagai span "tabular-nums" — dicari lewat itu, bukan
 * lewat isi teksnya saja, karena elemen leluhur ikut "mengandung" teks yang
 * sama dan membuat pencarian berbasis teks menemukan lebih dari satu. */
function amountSpans(container: HTMLElement): string[] {
  return Array.from(container.querySelectorAll("span.tabular-nums")).map(
    (el) => el.textContent ?? "",
  )
}

/**
 * Di layar lebar panel ini tidak berada di dalam drawer, jadi daftar pasien
 * dan terapis di-portal ke `<body>`. Pernah rusak karena halamannya mengirim
 * `popupContainer={null}` untuk keadaan itu — dan `null` bagi Base UI berarti
 * "wadahnya belum terpasang", bukan "portal ke body", sehingga popupnya tidak
 * pernah dirender. Di layar, fieldnya menerima fokus tapi tidak menampilkan
 * apa pun: persis seperti kolom yang tidak bisa diklik.
 */
describe("PosCheckoutPanel", () => {
  afterEach(cleanup)

  it("membuka daftar pasien saat tidak ada wadah portal khusus", async () => {
    renderPanel(<Harness />)

    fireEvent.click(screen.getAllByRole("button", { name: "" })[0])

    expect(await screen.findByText("Ibu Sinta")).toBeTruthy()
  })

  it("menawarkan daftar kunjungan juga", async () => {
    renderPanel(<Harness bookingOptions={[{ label: "12 Mei · Facial", value: "3" }]} />)

    fireEvent.click(screen.getAllByRole("button", { name: "" })[1])

    expect(await screen.findByText("12 Mei · Facial")).toBeTruthy()
  })

  it("tidak merender daftar saat wadahnya belum terpasang", async () => {
    // Kebalikannya, supaya arti `null` tetap terjaga: di dalam drawer,
    // popupnya memang harus menunggu wadahnya ada sebelum muncul.
    renderPanel(<Harness popupContainer={null} />)

    fireEvent.click(screen.getAllByRole("button", { name: "" })[0])

    expect(screen.queryByText("Ibu Sinta")).toBeNull()
  })

  /**
   * Manfaat member tidak boleh baru ketahuan saat nota sudah tercetak —
   * ditunjukkan begitu pasiennya dipilih, sebelum kasir sempat menghitung
   * sendiri.
   */
  it("menunjukkan badge dan perkiraan potongan saat pasiennya member", () => {
    const renderResult = renderPanel(
      <Harness
        membership={{
          id: 1,
          name: "Gold",
          discount_type: "percent",
          discount_value: 10,
          stacks_with_promo: false,
        }}
        items={[
          {
            key: "service:1",
            kind: "service",
            refId: 1,
            name: "Facial",
            unitPrice: 200_000,
            basePrice: null,
            promoName: null,
            qty: 1,
            stock: null,
            offeredBy: null,
          },
        ]}
        total={200_000}
      />,
    )

    const { container } = renderResult
    expect(screen.getByText("Gold")).toBeTruthy()
    expect(
      amountSpans(container).some((text) => text.includes("20.000")),
    ).toBe(true)
  })

  it("tidak menampilkan apa pun saat pasiennya bukan member", () => {
    renderPanel(<Harness />)

    expect(screen.queryByText("Gold")).toBeNull()
  })

  /**
   * Yang dibayar adalah jumlah setelah potongan member: kembalian dan sisa
   * tagihan harus dihitung dari angka yang sama dengan yang ditagih, bukan
   * dari total keranjang sebelum potongan.
   */
  it("mengurangi total pembayaran dengan potongan member", () => {
    const { container } = renderPanel(
      <Harness
        membership={{
          id: 1,
          name: "Gold",
          discount_type: "percent",
          discount_value: 10,
          stacks_with_promo: false,
        }}
        items={[
          {
            key: "service:1",
            kind: "service",
            refId: 1,
            name: "Facial",
            unitPrice: 200_000,
            basePrice: null,
            promoName: null,
            qty: 1,
            stock: null,
            offeredBy: null,
          },
        ]}
        total={200_000}
      />,
    )

    // 200.000 dikurangi 10% member = 180.000, bukan 200.000. Baris Total di
    // panel Pembayaran wajib menampilkan 180.000 — keranjang di atasnya tetap
    // menyebut 200.000 apa adanya, karena itu bukan yang ditagihkan.
    const spans = amountSpans(container)
    expect(spans.some((text) => text.includes("180.000"))).toBe(true)
  })
})

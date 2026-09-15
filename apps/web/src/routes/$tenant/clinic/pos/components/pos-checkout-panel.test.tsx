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
    member_active: "Tingkat member pasien ini.",
    loyalty_balance: "Poin saat ini",
    loyalty_points_unit: "poin",
    points_redeem: "Tukar Poin",
    points_redeem_all: "Tukar semua",
    points_redeem_hint: "Tiap poin memotong Rp1.000 dari tagihan.",
    points_capped: "Poin yang dipakai menyesuaikan tagihan.",
    cart: { title: "Keranjang" },
  },
  commission: { therapist: "Terapis" },
})

const PATIENTS = [
  { label: "Ibu Sinta", value: "1" },
  { label: "Pak Budi", value: "2" },
]

/** Satu baris keranjang seharga sekian — sisanya tidak diperiksa tes ini. */
function cartLine(unitPrice: number) {
  return {
    key: "service:1",
    kind: "service" as const,
    refId: 1,
    name: "Facial",
    unitPrice,
    basePrice: null,
    promoName: null,
    qty: 1,
    stock: null,
    offeredBy: null,
  }
}

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

  /** Tingkat member ditunjukkan begitu pasiennya dipilih, sebagai label saja. */
  it("menunjukkan badge tingkat member saat pasiennya member", () => {
    renderPanel(<Harness membership={{ id: 1, name: "Gold" }} />)

    expect(screen.getByText("Gold")).toBeTruthy()
  })

  it("tidak menampilkan apa pun saat pasiennya bukan member", () => {
    renderPanel(<Harness />)

    expect(screen.queryByText("Gold")).toBeNull()
  })

  /** Poin berlaku untuk pasien mana pun, bukan cuma member. */
  it("menunjukkan saldo poin begitu pasiennya dipilih", () => {
    renderPanel(<Harness loyaltyPoints={42} />)

    expect(screen.getByText("Poin saat ini")).toBeTruthy()
    expect(screen.getByText("42")).toBeTruthy()
  })

  it("tidak menampilkan baris poin sebelum pasiennya dipilih", () => {
    renderPanel(<Harness />)

    expect(screen.queryByText("Poin saat ini")).toBeNull()
  })

  /** Perkiraan poin dari keranjang saat ini, dihitung dari yang benar-benar dibayar. */
  it("menunjukkan perkiraan poin yang akan didapat dari transaksi ini", () => {
    renderPanel(
      <Harness
        loyaltyPoints={0}
        items={[cartLine(105_000)]}
        total={105_000}
      />,
    )

    // floor(105.000 / 10.000) = 10 poin.
    expect(screen.getByText("+10 poin")).toBeTruthy()
  })

  /**
   * Yang dibayar adalah jumlah setelah penukaran poin: kembalian dan sisa
   * tagihan harus dihitung dari angka yang sama dengan yang ditagih, bukan
   * dari total keranjang sebelum poinnya dipakai.
   */
  it("mengurangi total pembayaran dengan poin yang ditukar", () => {
    const { container } = renderPanel(
      <Harness
        loyaltyPoints={50}
        redeemPoints="30"
        onRedeemPointsChange={() => {}}
        items={[cartLine(200_000)]}
        total={200_000}
      />,
    )

    // 200.000 dikurangi 30 poin x Rp1.000 = 170.000. Keranjang di atasnya
    // tetap menyebut 200.000 apa adanya, karena itu bukan yang ditagihkan.
    const spans = Array.from(
      container.querySelectorAll("span.tabular-nums"),
    ).map((el) => el.textContent ?? "")

    expect(spans.some((text) => text.includes("170.000"))).toBe(true)
  })

  /** Poin yang dipakai ikut memangkas perkiraan poin yang akan didapat. */
  it("menghitung perkiraan poin dari yang benar-benar dibayar", () => {
    renderPanel(
      <Harness
        loyaltyPoints={50}
        redeemPoints="30"
        onRedeemPointsChange={() => {}}
        items={[cartLine(200_000)]}
        total={200_000}
      />,
    )

    // floor(170.000 / 10.000) = 17 poin, bukan 20 dari harga penuh.
    expect(screen.getByText("+17 poin")).toBeTruthy()
  })

  it("tidak menawarkan penukaran saat pasiennya belum punya poin", () => {
    renderPanel(
      <Harness
        loyaltyPoints={0}
        onRedeemPointsChange={() => {}}
        items={[cartLine(200_000)]}
        total={200_000}
      />,
    )

    expect(screen.queryByLabelText("Tukar Poin")).toBeNull()
  })
})

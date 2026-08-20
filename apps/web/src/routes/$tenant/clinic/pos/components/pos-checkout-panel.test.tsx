import { afterEach, describe, expect, it } from "vitest"
import { cleanup, fireEvent, render, screen } from "@testing-library/react"
import { useForm } from "react-hook-form"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { PosCheckoutPanel, type PatientFormValues } from "./pos-checkout-panel.tsx"
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

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
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
})

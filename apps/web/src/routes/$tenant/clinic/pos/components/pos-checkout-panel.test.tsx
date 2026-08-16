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
    defaultValues: { patient_id: "", therapist_id: "" },
  })

  return (
    <PosCheckoutPanel
      tenant="demo"
      form={form}
      patientOptions={PATIENTS}
      therapistOptions={[{ label: "Mbak Rara", value: "9" }]}
      created={null}
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

  it("membuka daftar terapis juga", async () => {
    renderPanel(<Harness />)

    fireEvent.click(screen.getAllByRole("button", { name: "" })[1])

    expect(await screen.findByText("Mbak Rara")).toBeTruthy()
  })

  it("tidak merender daftar saat wadahnya belum terpasang", async () => {
    // Kebalikannya, supaya arti `null` tetap terjaga: di dalam drawer,
    // popupnya memang harus menunggu wadahnya ada sebelum muncul.
    renderPanel(<Harness popupContainer={null} />)

    fireEvent.click(screen.getAllByRole("button", { name: "" })[0])

    expect(screen.queryByText("Ibu Sinta")).toBeNull()
  })
})

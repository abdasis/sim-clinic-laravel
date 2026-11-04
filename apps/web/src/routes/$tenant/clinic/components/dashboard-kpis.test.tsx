import { afterEach, describe, expect, it } from "vitest"
import { cleanup, render, screen } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { DashboardKpiRow } from "./dashboard-kpis.tsx"


const KPIS = {
  revenue_today: 12_450_000,
  bookings_today: 8,
  new_patients_7d: 14,
  outstanding_amount: 2_300_000,
  low_stock_count: 3,
}

function tampil(props: Partial<React.ComponentProps<typeof DashboardKpiRow>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <DashboardKpiRow kpis={KPIS as never} {...props} />
    </QueryClientProvider>,
  )
}

const grid = () => document.querySelector("div.grid") as HTMLElement

afterEach(cleanup)

/**
 * Tangga kolom baris KPI.
 *
 * Dulu hanya dua titik: dua kolom sejak `sm`, lima kolom sejak `lg`. Akibatnya
 * tablet 768-1023px selalu dapat dua kolom — lima KPI jadi menara panjang —
 * lalu di 1024px tiba-tiba lima kolom di area konten ~700px, tinggal 140px
 * per kartu, dan nominal rupiah panjang membungkus jadi dua baris.
 */
describe("tangga kolom baris KPI", () => {
  it("menambah titik md supaya tablet dapat tiga kolom", () => {
    tampil()

    expect(grid().className).toContain("sm:grid-cols-2")
    expect(grid().className).toContain("md:grid-cols-3")
  })

  /** Lima kolom menunggu sampai xl, bukan lg: di 1024px ruangnya tidak cukup. */
  it("menunda lima kolom sampai xl", () => {
    tampil()

    expect(grid().className).toContain("xl:grid-cols-5")
    expect(grid().className).not.toContain("lg:grid-cols-5")
  })

  /** Kerangka muatnya harus bertangga sama, kalau tidak layoutnya meloncat. */
  it("memakai tangga yang sama saat masih memuat", () => {
    cleanup()
    tampil({ kpis: undefined, isLoading: true })

    expect(grid().className).toContain("md:grid-cols-3")
    expect(grid().className).toContain("xl:grid-cols-5")
  })
})

/**
 * Nominal rupiah dibawa dalam dua bentuk sekaligus; yang dipilih container
 * query pada kartunya sendiri, bukan lebar layar. Lima kartu di 1280px dan
 * tiga kartu di 820px sama-sama lega; yang sempit lima kartu di 1024px —
 * dan hanya kartunya yang tahu bedanya.
 */
describe("nominal di kartu sempit", () => {
  it("membawa bentuk penuh dan bentuk pendek untuk nilai rupiah", () => {
    tampil()

    expect(screen.getByText("Rp12,5 jt")).toBeTruthy()
    expect(screen.getByText(/12\.450\.000/)).toBeTruthy()
  })

  /** Yang dipendekkan hanya tampilannya; angka pastinya tetap bisa dibaca. */
  it("menyimpan nominal penuh di title", () => {
    tampil()

    const nilai = screen.getByText("Rp12,5 jt").closest("p")

    expect(nilai?.getAttribute("title")).toMatch(/12\.450\.000/)
  })

  /** Kartu berisi hitungan tidak punya apa pun untuk dipendekkan. */
  it("tidak memendekkan kartu yang isinya hitungan", () => {
    tampil()

    const hitungan = screen.getByText("8").closest("p")

    expect(hitungan?.getAttribute("title")).toBeNull()
  })

  it("menukar keduanya lewat container query, bukan ambang layar", () => {
    tampil()

    const pendek = screen.getByText("Rp12,5 jt")
    const penuh = screen.getByText(/12\.450\.000/)

    expect(pendek.className).toContain("@max-[10rem]/tile:inline")
    expect(penuh.className).toContain("@max-[10rem]/tile:hidden")
  })

  it("menjadikan kartunya container supaya querynya punya acuan", () => {
    tampil()

    const kartu = screen.getByText("Rp12,5 jt").closest("article")

    expect(kartu?.className).toContain("@container/tile")
  })
})

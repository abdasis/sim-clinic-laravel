import type { ReactElement } from "react"
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { PromoTargetPicker } from "./promo-target-picker.tsx"

const TRANSLATIONS = {
  promo: {
    targets: "Layanan / Produk",
    targets_count: ":count dipilih",
    no_targets: "Belum ada yang dipilih",
    services: "Layanan",
    products: "Produk",
    pick_targets: "Cari layanan atau produk",
    no_target_match: "Tidak ada yang cocok",
  },
  general: {
    delete: "Hapus",
    load_failed: "Data gagal dimuat",
    retry: "Muat ulang",
  },
}

setTranslations(TRANSLATIONS)

/** Permintaan katalog yang tercatat, untuk memeriksa apa yang dikirim ke server. */
const panggilan: Array<{ path: string; params: Record<string, unknown> }> = []
let jawab: (path: string, params: Record<string, unknown>) => Promise<unknown>

vi.mock("#/lib/api.ts", () => ({
  apiGet: (path: string, params: Record<string, unknown> = {}) => {
    // useTrans memuat terjemahan lewat jalur yang sama; tanpa dipisah, ia
    // menimpa seluruh kamus dengan isi katalog.
    if (path.includes("/translations")) {
      return Promise.resolve({ data: TRANSLATIONS })
    }

    panggilan.push({ path, params })

    return jawab(path, params)
  },
}))

function katalog(nama: string[]) {
  return {
    data: nama.map((name, index) => ({
      id: index + 1,
      name,
      price: "100000",
    })),
  }
}

function renderPicker(ui: ReactElement) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function picker(onChange = vi.fn()) {
  renderPicker(
    <PromoTargetPicker tenant="klinik" value={[]} onChange={onChange} />,
  )

  return onChange
}

beforeEach(() => {
  panggilan.length = 0
  jawab = () => Promise.resolve(katalog(["Facial Glow", "Laser CO2"]))
})

afterEach(cleanup)

/**
 * Pemilih sasaran promo — layar yang dilaporkan "tambah layanan belum bisa".
 *
 * Dua sebabnya sama-sama tidak terlihat sebagai kerusakan: katalog dipotong di
 * 100 baris sementara pencariannya dikerjakan di browser, dan permintaan yang
 * gagal tampil persis seperti katalog kosong.
 */
describe("PromoTargetPicker", () => {
  it("menampilkan katalog dari server", async () => {
    picker()

    expect(await screen.findByText("Facial Glow")).toBeTruthy()
    expect(screen.getByText("Laser CO2")).toBeTruthy()
  })

  /**
   * Server memangkas per_page ke 100. Meminta lebih banyak tidak menambah
   * apa pun, dan menyaring di browser membuat layanan ke-101 mustahil dipilih.
   */
  it("tidak meminta lebih banyak dari yang server berikan", async () => {
    picker()

    await screen.findByText("Facial Glow")

    expect(panggilan[0].params.per_page).toBe(100)
  })

  it("menyerahkan pencarian ke server, bukan menyaring di browser", async () => {
    picker()

    await screen.findByText("Facial Glow")
    // Layanan yang tidak ada di halaman pertama hanya bisa ditemukan server.
    jawab = () => Promise.resolve(katalog(["Peeling Ke-150"]))

    fireEvent.change(screen.getByLabelText("Cari layanan atau produk"), {
      target: { value: "peeling" },
    })

    await waitFor(
      () => {
        expect(panggilan.at(-1)?.params.search).toBe("peeling")
      },
      { timeout: 2000 },
    )

    expect(await screen.findByText("Peeling Ke-150")).toBeTruthy()
  })

  /** Kata kunci kosong tidak dikirim sebagai string kosong. */
  it("tidak mengirim parameter pencarian saat kolomnya kosong", async () => {
    picker()

    await screen.findByText("Facial Glow")

    expect(panggilan[0].params.search).toBeUndefined()
  })

  /**
   * Inti keluhannya: selama gagal memuat berbunyi "tidak ada yang cocok",
   * yang gagal terbaca sebagai layanannya memang tidak ada.
   */
  it("membedakan gagal memuat dari katalog kosong", async () => {
    jawab = () => Promise.reject(new Error("500"))
    picker()

    expect(await screen.findByText("Data gagal dimuat")).toBeTruthy()
    expect(screen.queryByText("Tidak ada yang cocok")).toBeNull()
    expect(screen.getByRole("button", { name: "Muat ulang" })).toBeTruthy()
  })

  it("tetap mengatakan kosong saat katalognya memang kosong", async () => {
    jawab = () => Promise.resolve(katalog([]))
    picker()

    expect(await screen.findByText("Tidak ada yang cocok")).toBeTruthy()
    expect(screen.queryByText("Data gagal dimuat")).toBeNull()
  })

  it("meneruskan layanan yang dicentang ke pemanggilnya", async () => {
    const onChange = picker()

    await screen.findByText("Facial Glow")
    fireEvent.click(screen.getAllByRole("checkbox")[0])

    expect(onChange).toHaveBeenCalledWith([
      { type: "service", id: 1, name: "Facial Glow" },
    ])
  })
})

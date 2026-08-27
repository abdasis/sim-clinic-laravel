import type { ReactElement } from "react"
import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"

const TRANSLATIONS = {
  broadcast: {
    pick_contacts_hint: "Cari nama pasien, lalu tekan untuk memilihnya.",
    clear_selection: "Kosongkan pilihan",
    search_patients: "Cari nama atau nomor pasien",
    no_patient_match: "Tidak ada pasien yang cocok.",
  },
}

setTranslations(TRANSLATIONS)

const patients = [
  { id: 1, name: "Ani Lestari", whatsapp: "081100000001" },
  { id: 2, name: "Budi Santoso", whatsapp: "081100000002" },
  { id: 3, name: "Citra Dewi", whatsapp: "081100000003" },
]

vi.mock("#/lib/api.ts", () => ({
  apiGet: vi.fn((url: string, params?: { search?: string }) => {
    if (url.includes("/translations")) return Promise.resolve({ data: TRANSLATIONS })

    const search = params?.search?.toLowerCase()

    return Promise.resolve({
      data: search
        ? patients.filter((row) => row.name.toLowerCase().includes(search))
        : patients,
    })
  }),
}))

const { ContactPicker } = await import("./contact-picker.tsx")

function renderPicker(props: {
  value?: number[]
  onChange?: (ids: number[]) => void
}): ReactElement {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })

  render(
    <QueryClientProvider client={client}>
      <ContactPicker
        tenant="klinik-uji"
        value={props.value ?? []}
        onChange={props.onChange ?? (() => {})}
        enabled
      />
    </QueryClientProvider>,
  )

  return <></>
}

/**
 * Pemilih kontak untuk broadcast ke sekumpulan orang tertentu — yang paling
 * sering dibutuhkan tapi tidak punya jalan sebelumnya.
 */
describe("ContactPicker", () => {
  it("menawarkan pasien untuk dipilih", async () => {
    renderPicker({})

    await waitFor(() => expect(screen.getByText("Ani Lestari")).toBeTruthy())
    expect(screen.getByText("Budi Santoso")).toBeTruthy()
  })

  it("menyerahkan id yang ditekan", async () => {
    const onChange = vi.fn()
    renderPicker({ onChange })

    await waitFor(() => expect(screen.getByText("Ani Lestari")).toBeTruthy())
    fireEvent.click(screen.getByText("Ani Lestari"))

    expect(onChange).toHaveBeenCalledWith([1])
  })

  it("melepas kembali yang ditekan dua kali", async () => {
    const onChange = vi.fn()
    renderPicker({ value: [1], onChange })

    await waitFor(() => expect(screen.getByText("Budi Santoso")).toBeTruthy())
    fireEvent.click(screen.getByText("Ani Lestari"))

    expect(onChange).toHaveBeenCalledWith([])
  })

  it("menandai yang sudah terpilih", async () => {
    renderPicker({ value: [2] })

    await waitFor(() => expect(screen.getByText("Budi Santoso")).toBeTruthy())

    const budi = screen.getByText("Budi Santoso").closest("button")
    const ani = screen.getByText("Ani Lestari").closest("button")

    expect(budi?.getAttribute("aria-pressed")).toBe("true")
    expect(ani?.getAttribute("aria-pressed")).toBe("false")
  })

  /**
   * Setelah mengetik pencarian baru, daftarnya berganti isi. Pilihan lama
   * harus tetap terbaca — kalau tidak, admin kehilangan jejak siapa saja
   * yang sudah ia pilih.
   */
  it("tetap menampilkan pilihan yang tergeser oleh pencarian", async () => {
    const onChange = vi.fn()
    const { rerender } = render(<></>)

    const client = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    })

    const tree = (value: number[]) => (
      <QueryClientProvider client={client}>
        <ContactPicker
          tenant="klinik-uji"
          value={value}
          onChange={onChange}
          enabled
        />
      </QueryClientProvider>
    )

    rerender(tree([]))
    await waitFor(() => expect(screen.getByText("Ani Lestari")).toBeTruthy())

    fireEvent.click(screen.getByText("Ani Lestari"))
    rerender(tree([1]))

    fireEvent.change(screen.getByPlaceholderText("Cari nama atau nomor pasien"), {
      target: { value: "citra" },
    })

    // Ani tidak lagi ada di hasil pencarian, tapi kepingnya tetap terbaca.
    await waitFor(() =>
      expect(screen.queryByText("Citra Dewi")).toBeTruthy(),
    )
    expect(screen.getByText("Ani Lestari")).toBeTruthy()
  })

  it("mengosongkan seluruh pilihan sekaligus", async () => {
    const onChange = vi.fn()
    renderPicker({ value: [1, 2], onChange })

    await waitFor(() => expect(screen.getByText("Kosongkan pilihan")).toBeTruthy())
    fireEvent.click(screen.getByText("Kosongkan pilihan"))

    expect(onChange).toHaveBeenCalledWith([])
  })
})

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { CategoryFormDialog } from "./category-form-dialog.tsx"

const TRANSLATIONS = {
  category: {
    add: "Tambah Kategori",
    edit: "Ubah Kategori",
    name: "Nama Kategori",
    type: "Jenis",
    type_service: "Layanan",
    type_product: "Produk",
    type_expense: "Pengeluaran",
    type_locked: "Jenis tidak bisa diubah setelah kategori dibuat.",
    description: "Keterangan",
    status: "Status",
    created: "Kategori berhasil ditambahkan.",
    updated: "Kategori berhasil diperbarui.",
    empty_desc: "Kategori membantu mengelompokkan katalog.",
  },
  service: { status_active: "Aktif", status_archived: "Arsip" },
  general: { save: "Simpan" },
}

function mockFetch(response: { ok: boolean; status: number; body: unknown }) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init

    if (String(input).includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    return { ok: response.ok, status: response.status, json: async () => response.body }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function wrap(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function lastWrite(mock: ReturnType<typeof mockFetch>) {
  const call = mock.mock.calls.find(([, init]) =>
    ["POST", "PUT"].includes((init as RequestInit | undefined)?.method ?? ""),
  )

  return call
    ? {
        method: (call[1] as RequestInit).method,
        body: JSON.parse(String((call[1] as RequestInit).body)),
      }
    : null
}

describe("CategoryFormDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(cleanup)

  it("mengirim POST dengan jenis yang sedang dilihat", async () => {
    const fetchMock = mockFetch({ ok: true, status: 201, body: { data: { id: 1 } } })

    wrap(
      <CategoryFormDialog
        tenant="klinik-uji"
        defaultType="product"
        open
        onOpenChange={() => {}}
      />,
    )

    fireEvent.change(screen.getByLabelText(/Nama Kategori/), {
      target: { value: "Serum" },
    })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      expect(lastWrite(fetchMock)).toEqual({
        method: "POST",
        body: {
          name: "Serum",
          type: "product",
          description: "",
          status: "active",
        },
      })
    })
  })

  /**
   * Memindahkan kategori antar jenis akan melepasnya dari item yang sudah
   * memakainya, jadi jenisnya dikunci begitu kategorinya ada.
   */
  it("mengunci jenis saat mengubah kategori yang sudah ada", async () => {
    mockFetch({ ok: true, status: 200, body: { data: { id: 7 } } })

    wrap(
      <CategoryFormDialog
        tenant="klinik-uji"
        category={{
          id: 7,
          name: "Serum",
          type: "product",
          description: null,
          status: "active",
        }}
        open
        onOpenChange={() => {}}
      />,
    )

    await waitFor(() =>
      expect((screen.getByLabelText(/Nama Kategori/) as HTMLInputElement).value).toBe(
        "Serum",
      ),
    )

    expect((screen.getByLabelText(/Jenis/) as HTMLSelectElement).disabled).toBe(true)
  })

  it("mengirim PUT saat mengubah, bukan POST", async () => {
    const fetchMock = mockFetch({ ok: true, status: 200, body: { data: { id: 7 } } })

    wrap(
      <CategoryFormDialog
        tenant="klinik-uji"
        category={{
          id: 7,
          name: "Serum",
          type: "product",
          description: null,
          status: "active",
        }}
        open
        onOpenChange={() => {}}
      />,
    )

    const name = await screen.findByLabelText(/Nama Kategori/)
    fireEvent.change(name, { target: { value: "Serum Wajah" } })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => {
      const write = lastWrite(fetchMock)

      expect(write?.method).toBe("PUT")
      expect(write?.body.name).toBe("Serum Wajah")
      // Jenis terkunci di layar, tapi tetap ikut terkirim — server
      // memerlukannya untuk memeriksa nama ganda per jenis.
      expect(write?.body.type).toBe("product")
    })
  })

  it("menolak nama kosong sebelum dikirim", async () => {
    const fetchMock = mockFetch({ ok: true, status: 201, body: { data: { id: 1 } } })

    wrap(<CategoryFormDialog tenant="klinik-uji" open onOpenChange={() => {}} />)

    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => expect(lastWrite(fetchMock)).toBeNull())

    // Nama yang sah tetap terkirim lewat tombol yang sama — tanpa langkah ini
    // pemeriksaan di atas bisa lolos hanya karena permintaannya belum sempat
    // berangkat.
    fireEvent.change(screen.getByLabelText(/Nama Kategori/), {
      target: { value: "Skinbooster" },
    })
    fireEvent.click(screen.getByRole("button", { name: "Simpan" }))

    await waitFor(() => expect(lastWrite(fetchMock)?.body.name).toBe("Skinbooster"))
  })
})

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { ServiceFormDialog } from "./service-form-dialog.tsx"

const TRANSLATIONS = {
  service: {
    add: "Tambah Layanan",
    name: "Nama",
    category: "Kategori",
    description: "Deskripsi",
    price: "Harga",
    status: "Status",
    status_active: "Aktif",
    status_archived: "Arsip",
    created: "Layanan berhasil ditambahkan.",
  },
  general: { save: "Simpan" },
}

/**
 * `useTrans` ikut memanggil /translations. Tanpa dipisahkan, jawaban tiruan
 * untuk POST akan menimpa kamus dan label berubah jadi kunci mentah — test
 * lalu gagal karena alasan yang sama sekali berbeda dari yang diuji.
 */
function mockFetch(api: { ok: boolean; status: number; body: unknown }) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init
    if (String(input).includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    // Pilihan kategori dimuat dari server sekarang; tanpa jawaban ini,
    // formulirnya gagal karena alasan yang tidak sedang diuji.
    if (String(input).includes("/categories")) {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: [
            { id: 4, name: "Skinbooster" },
            { id: 5, name: "Facial & Peeling" },
          ],
        }),
      }
    }

    return { ok: api.ok, status: api.status, json: async () => api.body }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function wrap(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

function fillForm() {
  fireEvent.change(screen.getByLabelText("Nama"), {
    target: { value: "Facial Basic" },
  })
  fireEvent.change(screen.getByLabelText("Harga"), {
    target: { value: "250000" },
  })
  fireEvent.click(screen.getByRole("button", { name: "Simpan" }))
}

describe("ServiceFormDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  // Dialog Radix dirender lewat portal; tanpa ini sisa dialog test sebelumnya
  // ikut terbaca dan query label jadi ambigu.
  afterEach(cleanup)

  it("mengirim POST dengan isian form", async () => {
    const fetchMock = mockFetch({ ok: true, status: 201, body: { data: { id: 1 } } })

    wrap(<ServiceFormDialog tenant="demo" open onOpenChange={() => {}} />)
    fillForm()

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        (call) => (call[1] as RequestInit | undefined)?.method === "POST",
      )
      expect(post).toBeTruthy()
      expect(String(post![0])).toContain("/demo/clinic/services")
      expect(JSON.parse(String((post![1] as RequestInit).body))).toMatchObject({
        name: "Facial Basic",
        price: 250000,
      })
    })
  })

  /**
   * Inti keluhan yang memicu perbaikan ini: penolakan izin membuat tombol
   * simpan seolah tidak melakukan apa-apa. Alasannya sekarang wajib terbaca
   * di dalam form, bukan hanya lewat toast yang mudah terlewat.
   */
  it("menampilkan alasan saat server menolak tanpa error per-field", async () => {
    mockFetch({
      ok: false,
      status: 403,
      body: { message: "Anda tidak memiliki izin untuk mengakses modul ini." },
    })

    wrap(<ServiceFormDialog tenant="demo" open onOpenChange={() => {}} />)
    fillForm()

    expect(
      await screen.findByText("Anda tidak memiliki izin untuk mengakses modul ini."),
    ).toBeTruthy()
    expect(screen.getByRole("alert")).toBeTruthy()
  })

  /**
   * Kategori tidak ikut ditampilkan di tabel sebagai id, jadi kalau baris yang
   * dioper ke dialog tidak membawanya, formulir ubah terbuka tanpa kategori —
   * dan menyimpannya melepas kategori layanan itu tanpa ada yang meminta.
   */
  it("mengisi kategori tersimpan saat membuka mode ubah", async () => {
    mockFetch({ ok: true, status: 200, body: { data: { id: 9 } } })

    wrap(
      <ServiceFormDialog
        tenant="demo"
        service={{
          id: 9,
          name: "Facial Basic",
          category_id: 5,
          description: null,
          price: 250000,
          duration_minutes: 45,
          status: "active",
        }}
        open
        onOpenChange={() => {}}
      />,
    )

    await waitFor(() =>
      expect(
        (screen.getByLabelText("Kategori") as HTMLSelectElement).value,
      ).toBe("5"),
    )
  })

  it("error per-field tetap menempel di fieldnya, bukan di kepala form", async () => {
    mockFetch({
      ok: false,
      status: 422,
      body: {
        message: "Data tidak valid.",
        errors: { name: ["Nama layanan sudah dipakai."] },
      },
    })

    wrap(<ServiceFormDialog tenant="demo" open onOpenChange={() => {}} />)
    fillForm()

    expect(await screen.findByText("Nama layanan sudah dipakai.")).toBeTruthy()
    expect(screen.queryByRole("alert")).toBeNull()
  })
})

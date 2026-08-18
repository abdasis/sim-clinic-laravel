import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { setTranslations } from "#/utils/trans.ts"
import { CatalogImportDialog } from "./catalog-import-dialog.tsx"

const TRANSLATIONS = {
  import: {
    title: "Impor dari Template",
    template_hint: "Unduh template, isi, lalu unggah kembali.",
    download_template: "Unduh Template",
    upload: "Impor",
    select_file: "Pilih berkas",
    imported: "Baru ditambahkan",
    updated: "Diperbarui",
    errors: "Baris bermasalah",
    row: "Baris",
    field: "Kolom",
    message: "Keterangan",
    no_errors: "Semua baris masuk tanpa masalah.",
    name_conflict_hint: "Nama yang sudah ada akan diperbarui.",
  },
  general: { loading: "Memuat..." },
}

interface Result {
  imported: number
  updated: number
  errors: { row: number; field: string; message: string }[]
}

function mockFetch(result: Result) {
  const mock = vi.fn(async (input: unknown, init?: RequestInit) => {
    void init

    if (String(input).includes("/translations")) {
      return { ok: true, status: 200, json: async () => ({ data: TRANSLATIONS }) }
    }

    return { ok: true, status: 200, json: async () => ({ data: result }) }
  })

  vi.stubGlobal("fetch", mock)

  return mock
}

function open(result: Result) {
  const fetchMock = mockFetch(result)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <CatalogImportDialog
          tenant="klinik-uji"
          resource="services"
          templateName="template-layanan.xlsx"
          queryKey="services"
          open
          onOpenChange={() => {}}
        />
      </TooltipProvider>
    </QueryClientProvider>,
  )

  return fetchMock
}

function pickFile(name = "katalog.xlsx") {
  const input = document.querySelector('input[type="file"]') as HTMLInputElement
  const file = new File(["isi"], name, {
    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  })

  Object.defineProperty(input, "files", { value: [file], configurable: true })
  fireEvent.change(input)

  return file
}

const CLEAN: Result = { imported: 3, updated: 1, errors: [] }

describe("CatalogImportDialog", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations(TRANSLATIONS)
  })

  afterEach(cleanup)

  /** Tanpa berkas tidak ada yang bisa diunggah; tombolnya mati sampai ada. */
  it("mematikan tombol impor sampai berkas dipilih", () => {
    open(CLEAN)

    expect(screen.getByRole("button", { name: /Impor$/ })).toHaveProperty(
      "disabled",
      true,
    )

    pickFile()

    expect(screen.getByRole("button", { name: /Impor$/ })).toHaveProperty(
      "disabled",
      false,
    )
  })

  it("mengunggah berkas ke alamat modul yang benar", async () => {
    const fetchMock = open(CLEAN)

    pickFile()
    fireEvent.click(screen.getByRole("button", { name: /Impor$/ }))

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        ([, init]) => (init as RequestInit | undefined)?.method === "POST",
      )

      expect(post).toBeDefined()
      expect(String(post?.[0])).toContain("/klinik-uji/clinic/services/import")
      expect((post?.[1] as RequestInit).body).toBeInstanceOf(FormData)
    })
  })

  it("menampilkan ringkasan setelah impor selesai", async () => {
    open(CLEAN)

    pickFile()
    fireEvent.click(screen.getByRole("button", { name: /Impor$/ }))

    expect(await screen.findByText("Semua baris masuk tanpa masalah.")).toBeTruthy()
  })

  /**
   * Baris bermasalah harus disebut nomornya. Ringkasan tanpa rincian membuat
   * pengisi menebak-nebak baris mana yang perlu dibetulkan.
   */
  it("merinci baris yang bermasalah beserta nomornya", async () => {
    open({
      imported: 1,
      updated: 0,
      errors: [
        { row: 4, field: "harga", message: "Kolom harga harus berupa angka." },
        { row: 7, field: "nama", message: "Kolom nama wajib diisi." },
      ],
    })

    pickFile()
    fireEvent.click(screen.getByRole("button", { name: /Impor$/ }))

    expect(await screen.findByText("Kolom harga harus berupa angka.")).toBeTruthy()
    expect(screen.getByText("Kolom nama wajib diisi.")).toBeTruthy()
    expect(screen.getByText("4")).toBeTruthy()
    expect(screen.getByText("7")).toBeTruthy()
  })

  it("mengunduh template dari alamat modul yang benar", async () => {
    const fetchMock = open(CLEAN)

    fireEvent.click(screen.getByRole("button", { name: "Unduh Template" }))

    await waitFor(() =>
      expect(
        fetchMock.mock.calls.some((call) =>
          String(call[0]).includes("/klinik-uji/clinic/services/import/template"),
        ),
      ).toBe(true),
    )
  })
})

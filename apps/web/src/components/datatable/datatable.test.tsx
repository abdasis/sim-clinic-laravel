import { afterEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen } from "@testing-library/react"
import {
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from "@tanstack/react-table"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { DataTable } from "./datatable.tsx"

setTranslations({
  general: {
    search: "Cari",
    no_data: "Tidak ada data",
    no_data_desc: "Belum ada apa pun di sini.",
    no_results: "Tidak ada hasil",
    rows_per_page: "Baris per halaman",
    pagination_showing: "Menampilkan",
    pagination_of: "dari",
    load_failed: "Data gagal dimuat",
    load_failed_desc: "Server sedang tidak bisa dihubungi.",
    retry: "Muat ulang",
  },
})

interface Row {
  id: number
  name: string
}

const COLUMNS: ColumnDef<Row>[] = [{ accessorKey: "name", header: "Nama" }]

function Harness(props: Partial<React.ComponentProps<typeof DataTable<Row>>>) {
  const table = useReactTable({
    data: [],
    columns: COLUMNS,
    getCoreRowModel: getCoreRowModel(),
  })

  return <DataTable table={table} {...props} />
}

/** useTrans di dalam tabel memakai React Query, jadi providernya wajib ada. */
function renderTable(props: Partial<React.ComponentProps<typeof DataTable<Row>>>) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

/**
 * Gagal memuat dan benar-benar kosong adalah dua keadaan berbeda. Selama
 * keduanya terlihat sama, pengguna menyimpulkan datanya hilang padahal
 * servernya yang bermasalah — persis keluhan yang memicu perbaikan ini.
 */
describe("DataTable", () => {
  afterEach(cleanup)

  it("menyebut kegagalan saat permintaan daftar error", () => {
    renderTable({ isError: true })

    expect(screen.getByText("Data gagal dimuat")).toBeTruthy()
    expect(screen.queryByText("Tidak ada data")).toBeNull()
  })

  it("menawarkan muat ulang yang benar-benar memanggil balik", () => {
    const onRetry = vi.fn()
    renderTable({ isError: true, onRetry })

    fireEvent.click(screen.getByRole("button", { name: "Muat ulang" }))
    expect(onRetry).toHaveBeenCalledOnce()
  })

  it("tetap menampilkan keadaan kosong saat tidak ada error", () => {
    renderTable({})

    expect(screen.getByText("Tidak ada data")).toBeTruthy()
    expect(screen.queryByText("Data gagal dimuat")).toBeNull()
  })

  it("memuat lebih dulu, tanpa mengaku kosong maupun gagal", () => {
    renderTable({ isLoading: true, isError: true })

    expect(screen.queryByText("Data gagal dimuat")).toBeNull()
    expect(screen.queryByText("Tidak ada data")).toBeNull()
  })
})

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
  whatsapp?: string
  gender?: string
  referrer?: string
  points?: number
}

const COLUMNS: ColumnDef<Row>[] = [{ accessorKey: "name", header: "Nama" }]

/** Enam kolom: bentuk yang memaksa gulir dua arah di layar ponsel. */
const WIDE_COLUMNS: ColumnDef<Row>[] = [
  { accessorKey: "name", header: "Nama" },
  { accessorKey: "whatsapp", header: "WhatsApp" },
  { accessorKey: "gender", header: "Jenis Kelamin" },
  { accessorKey: "referrer", header: "Dibawa Oleh" },
  { accessorKey: "points", header: "Poin" },
  { id: "actions", header: "", cell: () => <button type="button">Aksi</button> },
]

const ROWS: Row[] = [
  {
    id: 1,
    name: "Ani Wijaya",
    whatsapp: "081200000001",
    gender: "Perempuan",
    referrer: "—",
    points: 190,
  },
]

function Harness(
  props: Partial<React.ComponentProps<typeof DataTable<Row>>> & {
    columns?: ColumnDef<Row>[]
    data?: Row[]
  },
) {
  const { columns = COLUMNS, data = [], ...rest } = props

  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
  })

  return <DataTable table={table} {...rest} />
}

/** Lebar viewport menentukan tier; jsdom default 1024 = desktop. */
function setViewport(width: number) {
  Object.defineProperty(window, "innerWidth", {
    configurable: true,
    writable: true,
    value: width,
  })
  window.dispatchEvent(new Event("resize"))
}

/** useTrans di dalam tabel memakai React Query, jadi providernya wajib ada. */
function renderTable(props: React.ComponentProps<typeof Harness>) {
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

/**
 * Tabel berkolom enam di layar ponsel memaksa gulir dua arah: kolom aksi di
 * ujung kanan tidak pernah terlihat tanpa menggulir, dan begitu digulir
 * header-nya lepas dari barisnya. Yang dijaga di sini pemilihan bentuknya —
 * kapan jadi kartu, kapan tetap tabel.
 */
describe("DataTable di layar sempit", () => {
  afterEach(() => {
    cleanup()
    setViewport(1024)
  })

  it("tetap tabel di desktop walau kolomnya banyak", () => {
    setViewport(1024)
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(document.querySelector("table")).toBeTruthy()
  })

  it("berubah jadi kartu di ponsel saat kolomnya lebih dari empat", () => {
    setViewport(390)
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(document.querySelector("table")).toBeNull()
    // Judul kartu memakai kolom identitas, sisanya jadi pasangan label-nilai.
    expect(screen.getByText("Ani Wijaya")).toBeTruthy()
    expect(screen.getByText("WhatsApp")).toBeTruthy()
    expect(screen.getByText("081200000001")).toBeTruthy()
  })

  /** Aksi baris harus ikut di kartunya, bukan hilang di ujung kanan. */
  it("membawa kolom aksi ke dalam kartu", () => {
    setViewport(390)
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(screen.getByRole("button", { name: "Aksi" })).toBeTruthy()
  })

  it("tetap tabel di ponsel saat kolomnya sedikit", () => {
    setViewport(390)
    renderTable({ columns: COLUMNS, data: ROWS })

    expect(document.querySelector("table")).toBeTruthy()
  })

  /** Kosong dan gagal harus tetap terbaca di mode kartu, bukan ikut hilang. */
  it("menampilkan keadaan kosong dan gagal di mode kartu", () => {
    setViewport(390)
    renderTable({ columns: WIDE_COLUMNS, data: [] })
    expect(screen.getByText("Tidak ada data")).toBeTruthy()

    cleanup()

    renderTable({ columns: WIDE_COLUMNS, data: [], isError: true })
    expect(screen.getByText("Data gagal dimuat")).toBeTruthy()
  })
})

/**
 * Yang menentukan bentuk tabel ruangnya, bukan ukuran perangkatnya.
 *
 * Di Galaxy Tab A (800px) ruang untuk tabel ikut berubah saat sidebar
 * dibuka-tutup: ~510px saat terbuka, ~710px saat jadi rel ikon. Layarnya sama
 * lebar di kedua keadaan, jadi tier layar saja tidak bisa menjawab keduanya
 * dengan benar — dan menjawabnya dengan menyembunyikan sidebar justru yang
 * dikeluhkan pengguna.
 */
describe("DataTable mengikuti lebar wadah", () => {
  afterEach(() => {
    cleanup()
    vi.restoreAllMocks()
    setViewport(1024)
  })

  function setContainerWidth(width: number) {
    vi.spyOn(HTMLElement.prototype, "getBoundingClientRect").mockReturnValue({
      width,
      height: 0,
      top: 0,
      left: 0,
      right: width,
      bottom: 0,
      x: 0,
      y: 0,
      toJSON: () => ({}),
    } as DOMRect)
  }

  it("jadi kartu di tablet saat sidebar terbuka menyisakan ruang sempit", () => {
    setViewport(800)
    setContainerWidth(510)
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(document.querySelector("table")).toBeNull()
    expect(screen.getByText("Ani Wijaya")).toBeTruthy()
  })

  it("kembali jadi tabel di tablet yang sama begitu sidebar diciutkan", () => {
    setViewport(800)
    setContainerWidth(710)
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(document.querySelector("table")).toBeTruthy()
  })
})

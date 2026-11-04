import { afterEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen } from "@testing-library/react"
import {
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from "@tanstack/react-table"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { TooltipProvider } from "#/components/ui/tooltip.tsx"
import { DataTable } from "./datatable.tsx"


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

/**
 * Bentuk yang sama, tapi dengan prioritas: yang menjelaskan mengalah lebih
 * dulu daripada yang mengidentifikasi.
 */
const PRIORITISED_COLUMNS: ColumnDef<Row>[] = [
  { accessorKey: "name", header: "Nama" },
  { accessorKey: "whatsapp", header: "WhatsApp", meta: { priority: 2 } },
  { accessorKey: "gender", header: "Jenis Kelamin", meta: { priority: 3 } },
  { accessorKey: "points", header: "Poin" },
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
      <TooltipProvider>
        <Harness {...props} />
      </TooltipProvider>
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


/**
 * Pita 640-1024px: tablet mendatar dan desktop dengan sidebar terbuka.
 *
 * Di sana tabelnya masih tabel — mode kartu hanya menjawab di bawah 640px —
 * dan enam kolom dengan judul Indonesia yang panjang saling menghimpit.
 * Kolom yang mengalah harus selalu yang menjelaskan, bukan yang
 * mengidentifikasi atau yang mengubah.
 */
describe("prioritas kolom", () => {
  afterEach(cleanup)

  function selOf(header: string): HTMLElement {
    const th = screen.getByText(header).closest("th")

    expect(th, `kolom "${header}" tidak ditemukan`).toBeTruthy()

    return th as HTMLElement
  }

  it("menyembunyikan kolom prioritas dua sampai md", () => {
    renderTable({ columns: PRIORITISED_COLUMNS, data: ROWS })

    expect(selOf("WhatsApp").className).toContain("hidden")
    expect(selOf("WhatsApp").className).toContain("md:table-cell")
  })

  it("menyembunyikan kolom prioritas tiga sampai lg", () => {
    renderTable({ columns: PRIORITISED_COLUMNS, data: ROWS })

    expect(selOf("Jenis Kelamin").className).toContain("lg:table-cell")
  })

  /** Tanpa prioritas berarti selalu tampil; itu bawaannya, bukan kelalaian. */
  it("tidak menyembunyikan kolom tanpa prioritas", () => {
    renderTable({ columns: PRIORITISED_COLUMNS, data: ROWS })

    expect(selOf("Nama").className).not.toContain("hidden")
    expect(selOf("Poin").className).not.toContain("hidden")
  })

  /** Selnya harus ikut sembunyi; kalau tidak, barisnya bergeser dari header. */
  it("menyembunyikan selnya bersama judulnya", () => {
    renderTable({ columns: PRIORITISED_COLUMNS, data: ROWS })

    const sel = screen.getByText("081200000001").closest("td")

    expect(sel?.className).toContain("hidden")
    expect(sel?.className).toContain("md:table-cell")
  })

  /**
   * Di mode kartu prioritas tidak berlaku: kartu tidak punya kolom, dan
   * menyembunyikan pasangan label-nilai di sana justru membuang data yang
   * sudah muat.
   */
  it("tidak berlaku di mode kartu", () => {
    setViewport(390)
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(screen.getByText("Jenis Kelamin")).toBeTruthy()
    expect(screen.getByText("Perempuan")).toBeTruthy()
    setViewport(1024)
  })
})

/**
 * Tabel yang tergulir mendatar tanpa penanda terbaca seperti tabel yang
 * memang hanya punya kolom sebanyak itu — kolom aksi di ujung kanan tidak
 * pernah terlihat, dan tidak ada apa pun yang memberi tahu bahwa ia ada.
 */
describe("petunjuk geser di tepi tabel", () => {
  afterEach(() => {
    cleanup()
    vi.restoreAllMocks()
  })

  function setScroll({ scrollWidth, clientWidth, scrollLeft }: {
    scrollWidth: number
    clientWidth: number
    scrollLeft: number
  }) {
    for (const [nama, nilai] of Object.entries({ scrollWidth, clientWidth, scrollLeft })) {
      Object.defineProperty(HTMLElement.prototype, nama, {
        configurable: true,
        get: () => nilai,
      })
    }
  }

  const wadah = () => document.querySelector('[data-slot="table-container"]')

  it("menandai sisa kolom di kanan saat belum tergulir", () => {
    setScroll({ scrollWidth: 900, clientWidth: 400, scrollLeft: 0 })
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(wadah()?.getAttribute("data-overflow-end")).toBe("true")
    expect(wadah()?.getAttribute("data-overflow-start")).toBeNull()
  })

  it("menandai kedua tepi saat tergulir di tengah", () => {
    setScroll({ scrollWidth: 900, clientWidth: 400, scrollLeft: 200 })
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(wadah()?.getAttribute("data-overflow-start")).toBe("true")
    expect(wadah()?.getAttribute("data-overflow-end")).toBe("true")
  })

  /** Tabel yang muat seluruhnya tidak boleh berbayang di tepi mana pun. */
  it("tidak menandai apa pun saat tabelnya muat", () => {
    setScroll({ scrollWidth: 400, clientWidth: 400, scrollLeft: 0 })
    renderTable({ columns: WIDE_COLUMNS, data: ROWS })

    expect(wadah()?.getAttribute("data-overflow-start")).toBeNull()
    expect(wadah()?.getAttribute("data-overflow-end")).toBeNull()
  })
})

/**
 * Target sentuh kontrol tabel. Berpindah halaman aksi yang paling sering
 * diketuk, dan 32px jauh di bawah ambang yang bisa dikenai jari dengan
 * yakin — salah ketuk di sana berarti kehilangan tempat.
 */
describe("kontrol tabel di layar sentuh", () => {
  afterEach(cleanup)

  it("menaikkan tombol halaman ke 44px", () => {
    renderTable({ columns: COLUMNS, data: ROWS, meta: { current_page: 1, per_page: 10, total: 30, last_page: 3 } })

    const tombol = screen.getByRole("button", { name: "Halaman berikutnya" })

    expect(tombol.className).toContain("touch:size-11")
  })

  it("menaikkan kolom pencarian dan pemilih kolom", () => {
    renderTable({ columns: COLUMNS, data: ROWS, searchPlaceholder: "Cari" })

    expect(screen.getByPlaceholderText("Cari").className).toContain("touch:h-10")
    expect(
      screen.getByRole("button", { name: "Kolom" }).className,
    ).toContain("touch:h-10")
  })

  /** Teks UI Indonesia, dan nama kolom sebagaimana tertulis di judulnya. */
  it("menamai pemilih kolom dalam bahasa Indonesia", () => {
    renderTable({ columns: COLUMNS, data: ROWS })

    expect(screen.queryByRole("button", { name: "View" })).toBeNull()
    expect(screen.getByRole("button", { name: "Kolom" })).toBeTruthy()
  })
})

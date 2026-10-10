import { flexRender, type Table } from "@tanstack/react-table"
import {
  Table as UiTable,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Button } from "#/components/ui/button.tsx"
import type { EmptyIllustrationName } from "#/components/ui/empty-illustration.tsx"
import { DataTableToolbar } from "#/components/datatable/datatable-toolbar.tsx"
import { DataTablePagination } from "#/components/datatable/datatable-pagination.tsx"
import { DataTableCards } from "#/components/datatable/datatable-cards.tsx"
import { useContainerWidth } from "#/hooks/use-container-width.ts"
import { isNarrowTier, useLayoutTier } from "#/hooks/use-mobile.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { DataTableMeta, FacetedOption } from "#/types/data-table.ts"

/**
 * Lebar wadah paling sempit yang masih menampung tabel klinik berkolom
 * banyak. Di bawah ini kolom-kolomnya saling menghimpit dan kolom aksi di
 * ujung kanan tidak pernah terlihat tanpa menggulir dua arah.
 */
const CARD_MAX_WIDTH = 640

interface DataTableProps<TData> {
  table: Table<TData>
  isLoading?: boolean
  searchPlaceholder?: string
  faceted?: Array<{ columnId: string; title: string; options: FacetedOption[] }>
  meta?: DataTableMeta
  /**
   * Pesan satu baris saat tabel kosong. Bentuk lama yang dipertahankan untuk
   * pemakai yang belum pindah; `emptyTitle` mengambil alih bila diisi.
   */
  emptyMessage?: string
  /** Judul keadaan kosong; mengaktifkan permukaan `Empty` yang lengkap. */
  emptyTitle?: string
  emptyDescription?: string
  emptyIllustration?: EmptyIllustrationName
  /** Aksi opsional di bawah deskripsi, mis. tombol tambah. */
  emptyAction?: React.ReactNode
  /**
   * Permintaan daftar gagal. Wajib diteruskan: tanpa ini tabel yang error
   * tampil persis seperti tabel kosong, dan pengguna menyimpulkan datanya
   * hilang padahal servernya yang bermasalah.
   */
  isError?: boolean
  onRetry?: () => void
  /**
   * Pesan dari server. Ditampilkan apa adanya karena backend sudah menulisnya
   * untuk dibaca manusia — mis. "jalankan php artisan migrate" — dan kalimat
   * umum justru menyembunyikan langkah yang perlu diambil.
   */
  error?: unknown
}

export function DataTable<TData>({
  table,
  isLoading,
  searchPlaceholder,
  faceted,
  meta,
  emptyMessage,
  emptyTitle,
  emptyDescription,
  isError,
  onRetry,
  error,
  emptyIllustration,
  emptyAction,
}: DataTableProps<TData>) {
  const { t } = useTrans()
  const tier = useLayoutTier()
  const [containerRef, containerWidth] = useContainerWidth<HTMLDivElement>()
  const rows = table.getRowModel().rows
  const columnCount = table.getAllColumns().length

  // Tabel sempit masih terbaca sebagai tabel; yang tidak terbaca adalah tabel
  // berkolom banyak di ruang sempit. Ambangnya jumlah kolom, bukan halamannya,
  // supaya tabel baru ikut tanpa harus didaftarkan satu per satu.
  //
  // Yang diukur wadahnya, bukan layarnya: di tablet 800px ruang untuk tabel
  // ikut berubah saat sidebar dibuka-tutup, dan tabel enam kolom di 510px
  // butuh kartu sementara di 710px masih muat. Sebelum terukur — di server
  // dan pada render pertama — tier layar yang menjawab, jadi ponsel langsung
  // mendapat kartu alih-alih berkedip dari tabel dulu.
  const asCards =
    (containerWidth === null ? isNarrowTier(tier) : containerWidth < CARD_MAX_WIDTH) &&
    table.getVisibleLeafColumns().length > 4

  const emptyState =
    !emptyTitle && emptyMessage ? (
      // Teks polos hanya bertahan untuk pemakai lama yang belum mengisi
      // emptyTitle; sisanya dapat permukaan penuh.
      <p className="text-center text-muted-foreground">{emptyMessage}</p>
    ) : (
      <EmptyState
        className="p-0"
        illustration={emptyIllustration}
        title={emptyTitle ?? t("general.no_data")}
        description={emptyDescription ?? t("general.no_data_desc")}
        action={emptyAction}
      />
    )

  // Gagal memuat bukan berarti datanya tidak ada — dua hal itu tidak boleh
  // terlihat sama.
  const errorState = (
    <EmptyState
      className="p-0"
      illustration="default"
      title={t("general.load_failed")}
      description={serverMessage(error) ?? t("general.load_failed_desc")}
      action={
        onRetry ? (
          <Button variant="outline" size="sm" onClick={onRetry}>
            {t("general.retry")}
          </Button>
        ) : undefined
      }
    />
  )

  return (
    <div ref={containerRef} className="space-y-2">
      <DataTableToolbar
        table={table}
        searchPlaceholder={searchPlaceholder}
        faceted={faceted}
      />
      {asCards ? (
        isLoading ? (
          <div className="space-y-2">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={`card-skeleton-${i}`} className="h-20 w-full rounded-md" />
            ))}
          </div>
        ) : isError ? (
          <div className="rounded-md border py-14">{errorState}</div>
        ) : rows.length === 0 ? (
          <div className="rounded-md border py-14">{emptyState}</div>
        ) : (
          <DataTableCards rows={rows} />
        )
      ) : (
      <div className="rounded-md border">
        <UiTable>
          <TableHeader>
            {table.getHeaderGroups().map((group) => (
              <TableRow key={group.id} className="bg-muted/30">
                {group.headers.map((header) => (
                  <TableHead key={header.id} colSpan={header.colSpan}>
                    {header.isPlaceholder
                      ? null
                      : (flexRender(header.column.columnDef.header, header.getContext()))}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <TableRow key={`skeleton-${i}`}>
                  {Array.from({ length: columnCount }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : isError ? (
              <TableRow className="hover:bg-transparent">
                <TableCell colSpan={columnCount} className="py-14">
                  {errorState}
                </TableCell>
              </TableRow>
            ) : rows.length === 0 ? (
              <TableRow className="hover:bg-transparent">
                <TableCell colSpan={columnCount} className="py-14">
                  {emptyState}
                </TableCell>
              </TableRow>
            ) : (
              rows.map((row) => (
                <TableRow key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </UiTable>
      </div>
      )}
      <DataTablePagination table={table} meta={meta} />
    </div>
  )
}

/** Ambil `message` dari ApiError bila ada; bentuk lain diabaikan. */
function serverMessage(error: unknown): string | undefined {
  if (typeof error !== "object" || error === null) return undefined

  const message = (error as { message?: unknown }).message

  return typeof message === "string" && message.trim() !== "" ? message : undefined
}

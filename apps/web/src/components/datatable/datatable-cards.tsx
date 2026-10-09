import { Fragment } from "react"
import { flexRender, type Column, type Row } from "@tanstack/react-table"

/**
 * Baris tabel sebagai kartu, untuk layar yang tidak cukup lebar untuk tabel.
 *
 * Tabel berkolom enam di layar 390px memaksa gulir dua arah: kolom aksi di
 * ujung kanan tidak pernah terlihat tanpa menggulir, dan begitu digulir
 * header-nya lepas dari barisnya. Kartu membuang masalah itu seluruhnya —
 * tiap baris berdiri sendiri, dan aksinya ikut di dalamnya.
 *
 * Kolom pertama jadi judul kartu karena di hampir semua tabel klinik itu
 * kolom identitasnya: nama pasien, nama produk, nomor nota. Kolom aksi
 * dinaikkan ke sudut kanan atas, sejajar judul, supaya jaraknya ke apa yang
 * diubah tetap dekat.
 */
export function DataTableCards<TData>({ rows }: { rows: Row<TData>[] }) {
  return (
    <ul className="divide-y divide-border/60 overflow-hidden rounded-md border">
      {rows.map((row) => {
        const cells = row.getVisibleCells()
        const actions = cells.find((cell) => cell.column.id === "actions")
        const [title, ...rest] = cells.filter(
          (cell) => cell.column.id !== "actions",
        )

        return (
          <li key={row.id} className="p-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0 flex-1 font-medium">
                {title
                  ? flexRender(title.column.columnDef.cell, title.getContext())
                  : null}
              </div>
              {actions ? (
                <div className="-mt-1 -mr-1 shrink-0">
                  {flexRender(
                    actions.column.columnDef.cell,
                    actions.getContext(),
                  )}
                </div>
              ) : null}
            </div>

            {rest.length > 0 ? (
              <dl className="mt-2 grid grid-cols-[minmax(0,auto)_minmax(0,1fr)] gap-x-3 gap-y-1 text-xs">
                {rest.map((cell) => (
                  <Fragment key={cell.id}>
                    <dt className="text-muted-foreground">
                      {columnLabel(cell.column)}
                    </dt>
                    <dd className="min-w-0 text-right">
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </dd>
                  </Fragment>
                ))}
              </dl>
            ) : null}
          </li>
        )
      })}
    </ul>
  )
}

/**
 * Label kolom untuk dipasang di depan nilainya.
 *
 * Judul kolom boleh berupa fungsi render (mis. tombol urut), dan yang begitu
 * tidak bisa dipakai sebagai teks label — dalam hal itu id kolomnya yang
 * dipakai, bukan merender tombol urut di dalam tiap kartu.
 */
function columnLabel<TData>(column: Column<TData>): string {
  const header = column.columnDef.header

  return typeof header === "string" && header !== "" ? header : column.id
}

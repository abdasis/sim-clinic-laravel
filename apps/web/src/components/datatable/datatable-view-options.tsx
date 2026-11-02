import { SlidersHorizontalIcon } from "lucide-react"
import type { Column, Table } from "@tanstack/react-table"
import { Button } from "#/components/ui/button.tsx"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface DataTableViewOptionsProps<TData> {
  table: Table<TData>
}

/**
 * Nama kolom sebagaimana tertulis di judulnya.
 *
 * Judul kolom boleh berupa fungsi render (tombol urut, mis.), dan yang begitu
 * tidak bisa dipakai sebagai teks — id kolomnya yang dipakai sebagai cadangan.
 * Tanpa ini daftarnya menampilkan `stock_balance` alih-alih "Saldo stok".
 */
function columnLabel<TData>(column: Column<TData>): string {
  const header = column.columnDef.header

  return typeof header === "string" && header !== "" ? header : column.id
}

export function DataTableViewOptions<TData>({
  table,
}: DataTableViewOptionsProps<TData>) {
  const { t } = useTrans()
  const columns = table
    .getAllColumns()
    .filter(
      (column) =>
        typeof column.accessorFn !== "undefined" && column.getCanHide(),
    )

  return (
    <DropdownMenu>
      <Tooltip>
        <TooltipTrigger asChild>
          <DropdownMenuTrigger asChild>
            {/* 32px cukup untuk kursor, tidak untuk jari; di layar sentuh
                tingginya naik ke 40px tanpa mengubah tampilan desktop. */}
            <Button
              variant="outline"
              size="sm"
              className="ml-auto h-8 touch:h-10"
            >
              <SlidersHorizontalIcon className="size-3.5" />
              {t("general.columns")}
            </Button>
          </DropdownMenuTrigger>
        </TooltipTrigger>
        {/* Tanpa lencana shortcut: belum ada yang terpasang untuk tombol ini,
            dan menampilkan tombol yang tidak bekerja lebih buruk daripada
            tidak menampilkan apa pun. */}
        <TooltipContent>{t("general.columns_hint")}</TooltipContent>
      </Tooltip>
      <DropdownMenuContent align="end" className="w-48">
        <DropdownMenuLabel>{t("general.columns_hint")}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {columns.map((column) => (
          <DropdownMenuCheckboxItem
            key={column.id}
            checked={column.getIsVisible()}
            onCheckedChange={(value) => column.toggleVisibility(!!value)}
          >
            {columnLabel(column)}
          </DropdownMenuCheckboxItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

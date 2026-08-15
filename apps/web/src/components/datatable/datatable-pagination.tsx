import type { Table } from "@tanstack/react-table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "#/components/ui/select.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { DataTableMeta } from "#/types/data-table.ts"

interface DataTablePaginationProps<TData> {
  table: Table<TData>
  meta?: DataTableMeta
}

export function DataTablePagination<TData>({
  table,
  meta,
}: DataTablePaginationProps<TData>) {
  const { t } = useTrans()
  const pageIndex = table.getState().pagination.pageIndex
  const pageSize = table.getState().pagination.pageSize
  const total = meta?.total ?? 0
  const start = total === 0 ? 0 : pageIndex * pageSize + 1
  const end = Math.min((pageIndex + 1) * pageSize, total)

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-2 py-3">
      <div className="text-sm text-muted-foreground">
        {/* t() hanya mengembalikan string, jadi angkanya dirangkai di sini. */}
        {total === 0
          ? t("general.no_results")
          : `${t("general.pagination_showing")} ${start}–${end} ${t("general.pagination_of")} ${total}`}
      </div>
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2 text-sm">
          <span className="hidden sm:inline">{t("general.rows_per_page")}</span>
          <Select
            value={String(pageSize)}
            onValueChange={(v) => table.setPageSize(Number(v))}
          >
            <SelectTrigger size="sm" className="h-8 w-[70px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {[5, 10, 20, 50].map((size) => (
                <SelectItem key={size} value={String(size)}>
                  {size}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-8 p-0"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            ‹
          </Button>
          <span className="text-sm">
            {meta ? meta.current_page : pageIndex + 1}
            {meta ? ` / ${meta.last_page}` : ""}
          </span>
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-8 p-0"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            ›
          </Button>
        </div>
      </div>
    </div>
  )
}
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
import { DataTableToolbar } from "#/components/datatable/datatable-toolbar.tsx"
import { DataTablePagination } from "#/components/datatable/datatable-pagination.tsx"
import type { DataTableMeta, FacetedOption } from "#/types/data-table.ts"

interface DataTableProps<TData> {
  table: Table<TData>
  isLoading?: boolean
  searchPlaceholder?: string
  faceted?: Array<{ columnId: string; title: string; options: FacetedOption[] }>
  meta?: DataTableMeta
}

export function DataTable<TData>({
  table,
  isLoading,
  searchPlaceholder,
  faceted,
  meta,
}: DataTableProps<TData>) {
  const rows = table.getRowModel().rows
  const columnCount = table.getAllColumns().length

  return (
    <div className="space-y-2">
      <DataTableToolbar
        table={table}
        searchPlaceholder={searchPlaceholder}
        faceted={faceted}
      />
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
            ) : rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={columnCount} className="h-24 text-center">
                  No results
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
      <DataTablePagination table={table} meta={meta} />
    </div>
  )
}
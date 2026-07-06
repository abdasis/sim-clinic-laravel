import { useEffect, useState } from "react"
import { SearchIcon } from "lucide-react"
import type { Table } from "@tanstack/react-table"
import { Input } from "#/components/ui/input.tsx"
import { DataTableViewOptions } from "#/components/datatable/datatable-view-options.tsx"
import { DataTableFacetedFilter } from "#/components/datatable/datatable-faceted-filter.tsx"
import type { FacetedOption } from "#/types/data-table.ts"

interface DataTableToolbarProps<TData> {
  table: Table<TData>
  searchPlaceholder?: string
  faceted?: Array<{ columnId: string; title: string; options: FacetedOption[] }>
}

export function DataTableToolbar<TData>({
  table,
  searchPlaceholder = "Search...",
  faceted,
}: DataTableToolbarProps<TData>) {
  const globalFilter = (table.getState().globalFilter as string) ?? ""
  const [value, setValue] = useState(globalFilter)

  useEffect(() => {
    setValue(globalFilter)
  }, [globalFilter])

  // Debounce global search 300ms sebelum kirim ke server.
  useEffect(() => {
    const timer = setTimeout(() => {
      table.setGlobalFilter(value)
    }, 300)
    return () => clearTimeout(timer)
  }, [value, table])

  return (
    <div className="flex flex-wrap items-center gap-2 py-2">
      <div className="relative">
        <SearchIcon className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={searchPlaceholder}
          className="h-8 w-40 pl-8 md:w-56"
        />
      </div>
      {faceted?.map((f) => (
        <DataTableFacetedFilter
          key={f.columnId}
          column={table.getColumn(f.columnId)}
          title={f.title}
          options={f.options}
        />
      ))}
      <DataTableViewOptions table={table} />
    </div>
  )
}
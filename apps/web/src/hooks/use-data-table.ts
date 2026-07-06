import { useMemo, useState } from "react"
import {
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
  type ColumnFiltersState,
  type PaginationState,
  type SortingState,
  type VisibilityState,
} from "@tanstack/react-table"
import { useQuery } from "@tanstack/react-query"
import type {
  DataTableParams,
  DataTableResponse,
} from "#/types/data-table.ts"

interface UseDataTableOptions<TData> {
  queryKey: unknown[]
  queryFn: (params: DataTableParams) => Promise<DataTableResponse<TData>>
  columns: ColumnDef<TData, unknown>[]
  initialPageSize?: number
}

export function useDataTable<TData>({
  queryKey,
  queryFn,
  columns,
  initialPageSize = 10,
}: UseDataTableOptions<TData>) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: initialPageSize,
  })
  const [sorting, setSorting] = useState<SortingState>([])
  const [globalFilter, setGlobalFilter] = useState("")
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([])
  const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({})

  const params = useMemo<DataTableParams>(() => {
    const sort = sorting[0]
    const filters: Record<string, string> = {}
    for (const f of columnFilters) {
      if (typeof f.value === "string" && f.value !== "") filters[f.id] = f.value
    }

    return {
      page: pagination.pageIndex + 1,
      per_page: pagination.pageSize,
      sort: sort?.id,
      direction: sort?.desc ? "desc" : "asc",
      search: globalFilter || undefined,
      filters: Object.keys(filters).length ? filters : undefined,
    }
  }, [pagination, sorting, globalFilter, columnFilters])

  const { data, isLoading, isError, error } = useQuery({
    queryKey: [...queryKey, params],
    queryFn: () => queryFn(params),
    placeholderData: (prev) => prev,
  })

  const pageCount = data?.meta?.last_page ?? 0

  const table = useReactTable({
    data: data?.data ?? [],
    columns,
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    enableGlobalFilter: true,
    pageCount,
    state: {
      pagination,
      sorting,
      globalFilter,
      columnFilters,
      columnVisibility,
    },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onColumnFiltersChange: setColumnFilters,
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
  })

  return {
    table,
    data: data?.data ?? [],
    meta: data?.meta,
    isLoading,
    isError,
    error,
  }
}
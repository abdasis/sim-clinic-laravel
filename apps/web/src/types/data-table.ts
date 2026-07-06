import type { ColumnDef } from "@tanstack/react-table"

export interface DataTableMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface DataTableResponse<TData> {
  data: TData[]
  meta: DataTableMeta
}

export interface DataTableParams {
  page: number
  per_page: number
  sort?: string
  direction?: "asc" | "desc"
  search?: string
  filters?: Record<string, string>
}

export type DataTableColumnDef<TData, TValue = unknown> = ColumnDef<TData, TValue>

export interface FacetedOption {
  label: string
  value: string
}
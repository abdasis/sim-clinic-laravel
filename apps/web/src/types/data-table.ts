import type { ColumnDef } from "@tanstack/react-table"

import type { ColumnPriority } from "#/components/datatable/column-priority.ts"

/**
 * Prioritas kolom dideklarasikan lewat `meta` pada definisi kolomnya:
 *
 *     { accessorKey: "created_at", header: "Dibuat", meta: { priority: 3 } }
 *
 * Augmentasi ini yang membuatnya terperiksa tipe — tanpa itu `meta` bertipe
 * `unknown` dan salah ketik pada namanya lolos tanpa suara.
 */
declare module "@tanstack/react-table" {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData extends unknown, TValue> {
    priority?: ColumnPriority
  }
}

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
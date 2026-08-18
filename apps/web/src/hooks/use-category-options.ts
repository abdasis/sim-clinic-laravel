import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"
import type { SelectOption } from "#/components/forms/form-select.tsx"

export type CategoryType = "service" | "product" | "expense"

export interface CategoryRow {
  id: number
  name: string
  type: CategoryType
  type_label: string
  description?: string | null
  status: string
  status_label: string
  usage_count?: number
}

/**
 * Pilihan kategori untuk satu jenis entitas.
 *
 * Hanya kategori aktif yang ditawarkan: yang diarsipkan tetap tampil pada item
 * yang sudah memakainya, tapi tidak boleh dipilih lagi untuk yang baru.
 *
 * `emptyLabel` menyediakan opsi "tanpa kategori"; jangan diisi untuk formulir
 * yang kategorinya wajib.
 */
export function useCategoryOptions(
  tenant: string,
  type: CategoryType,
  options: { enabled?: boolean; emptyLabel?: string } = {},
) {
  const { enabled = true, emptyLabel } = options

  const query = useQuery({
    queryKey: ["categories", tenant, type, "options"],
    queryFn: () =>
      apiGet<{ data: CategoryRow[] }>(`/${tenant}/clinic/categories`, {
        per_page: 200,
        filter: { type, status: "active" },
      }),
    enabled,
    staleTime: Infinity,
  })

  // Bentuk yang tak terduga tidak boleh merobohkan seluruh formulir; yang
  // terjadi paling jauh daftar pilihannya kosong.
  const rows = Array.isArray(query.data?.data) ? query.data.data : []

  const selectOptions: SelectOption[] = [
    ...(emptyLabel !== undefined ? [{ value: "", label: emptyLabel }] : []),
    ...rows.map((category) => ({
      value: String(category.id),
      label: category.name,
    })),
  ]

  return { ...query, options: selectOptions }
}

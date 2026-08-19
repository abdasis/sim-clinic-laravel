import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"
import type { SelectOption } from "#/components/forms/form-select.tsx"

export interface UnitRow {
  id: number
  name: string
  status: string
  status_label: string
  usage_count?: number
}

/**
 * Pilihan satuan untuk formulir produk.
 *
 * Hanya satuan aktif yang ditawarkan: yang diarsipkan tetap tampil pada
 * produk yang sudah memakainya, tapi tidak boleh dipilih lagi untuk yang
 * baru — server menolaknya dengan aturan yang sama.
 *
 * `emptyLabel` menyediakan opsi "tanpa satuan"; satuan memang boleh kosong,
 * jadi formulir produk selalu mengisinya.
 */
export function useUnitOptions(
  tenant: string,
  options: { enabled?: boolean; emptyLabel?: string } = {},
) {
  const { enabled = true, emptyLabel } = options

  const query = useQuery({
    queryKey: ["units", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: UnitRow[] }>(`/${tenant}/clinic/units`, {
        per_page: 200,
        filter: { status: "active" },
      }),
    enabled,
    staleTime: Infinity,
  })

  // Bentuk yang tak terduga tidak boleh merobohkan formulirnya; paling jauh
  // daftar pilihannya kosong.
  const rows = Array.isArray(query.data?.data) ? query.data.data : []

  const selectOptions: SelectOption[] = [
    ...(emptyLabel !== undefined ? [{ value: "", label: emptyLabel }] : []),
    ...rows.map((unit) => ({ value: String(unit.id), label: unit.name })),
  ]

  return { ...query, options: selectOptions }
}

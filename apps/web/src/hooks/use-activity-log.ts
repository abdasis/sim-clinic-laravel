import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"

/** Satu bidang yang berubah; `old` kosong berarti baru dibuat, `new` kosong berarti dihapus. */
export interface ActivityChange {
  field: string
  label: string
  old: unknown
  new: unknown
}

export interface ActivityRow {
  id: number
  logged_at: string | null
  module: string | null
  module_label: string
  event: string | null
  event_label: string
  description: string | null
  causer_id: number | null
  causer_name: string | null
  causer_email: string | null
  causer_role: string | null
  subject_id: number | null
  subject_type: string | null
  subject_type_label: string | null
  subject_name: string | null
  subject_missing: boolean
  changes: ActivityChange[]
  context: Record<string, unknown>
  has_details: boolean
}

export interface ActivityFilterOption {
  value: string
  label: string
}

export interface ActivityFilterOptions {
  modules: ActivityFilterOption[]
  events: Array<ActivityFilterOption & { module: string | null }>
  causers: ActivityFilterOption[]
}

/**
 * Isi penyaring datang dari server karena hanya server yang tahu modul dan
 * aksi apa saja yang pernah benar-benar terjadi di klinik ini. Daftarnya
 * berubah lambat, jadi disimpan lima menit.
 */
export function useActivityFilterOptions(tenant: string) {
  return useQuery({
    queryKey: ["activity-logs", tenant, "filters"],
    queryFn: () =>
      apiGet<{ data: ActivityFilterOptions }>(
        `/${tenant}/clinic/activity-logs/filters`,
      ),
    enabled: Boolean(tenant),
    staleTime: 300_000,
  })
}

import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"

/** Modul klinik yang punya ringkasan statistik; cocok dengan App\Enums\StatsModule. */
export type StatsModule =
  | "patients"
  | "services"
  | "products"
  | "inventory"
  | "medical-records"
  | "transactions"
  | "bookings"
  | "staff"
  | "activity-logs"

/** Satuan nilai KPI — menentukan cara angkanya diformat di kartu. */
export type StatUnit = "count" | "currency" | "qty"

export interface StatKpi {
  key: string
  label: string
  value: number
  unit: StatUnit
}

export interface StatPoint {
  date: string
  value: number
}

export interface StatsPayload {
  kpis: StatKpi[]
  trend: StatPoint[]
  trend_metric: string
  trend_label: string
}

export interface StatsMeta {
  module: string
  from: string
  to: string
  days?: number
  weeks?: number
}

export interface StatsResponse {
  data: StatsPayload
  meta: StatsMeta
}

/**
 * Angka ringkas ini menemani tabel, bukan menggantikannya — satu menit cukup
 * segar dan menghindari request ulang tiap kali halaman dibuka kembali.
 */
const STALE_TIME = 60_000

interface UseStatsOptions {
  tenant: string
  module: StatsModule
  days?: number
  /** Matikan saat peran pengguna tidak berhak membaca modulnya. */
  enabled?: boolean
}

export function useStats({
  tenant,
  module,
  days = 30,
  enabled = true,
}: UseStatsOptions) {
  return useQuery({
    queryKey: ["stats", tenant, module, days],
    queryFn: () =>
      apiGet<StatsResponse>(`/${tenant}/clinic/stats/${module}`, { days }),
    enabled: enabled && Boolean(tenant),
    staleTime: STALE_TIME,
  })
}

export function useCentralStats(enabled = true) {
  return useQuery({
    queryKey: ["stats", "central"],
    queryFn: () => apiGet<StatsResponse>("/central/stats"),
    enabled,
    staleTime: STALE_TIME,
  })
}

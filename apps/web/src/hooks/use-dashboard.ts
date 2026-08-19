import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"

export interface DashboardKpis {
  revenue_today: string
  bookings_today: number
  new_patients_7d: number
  outstanding_amount: string
  low_stock_count: number
}

export interface BookingStatusCount {
  status: string
  label: string
  count: number
}

export interface RevenuePoint {
  date: string
  revenue: string
}

export interface TopService {
  service_name: string
  qty: number
  revenue: string
}

export interface ScheduleEntry {
  id: number
  patient_name?: string | null
  service_name?: string | null
  assignee_name?: string | null
  start_at?: string | null
  end_at?: string | null
  status: string
  status_label?: string | null
}

export interface DashboardSummary {
  kpis: DashboardKpis
  booking_status_counts: BookingStatusCount[]
  revenue_trend: RevenuePoint[]
  top_services: TopService[]
  schedule_today: ScheduleEntry[]
}

export interface DashboardMeta {
  from: string
  to: string
  days: number
}

/**
 * Seluruh isi dasbor dalam satu permintaan — halaman menampilkan lima blok
 * sekaligus, jadi memecahnya jadi lima request hanya membuatnya berkedip.
 */
export function useDashboardSummary(tenant: string, days = 14, enabled = true) {
  return useQuery({
    queryKey: ["dashboard", tenant, days],
    queryFn: () =>
      apiGet<{ data: DashboardSummary; meta: DashboardMeta }>(
        `/${tenant}/clinic/dashboard/summary`,
        { days },
      ),
    // `enabled` diisi pemanggil dengan keadaan sudah-terpasang. Token ada di
    // localStorage, yang tidak terbaca saat halaman dirakit di server —
    // permintaan yang berangkat di sana pasti dijawab 401, dan hasilnya
    // bukan cuma kerja sia-sia: layar sempat menampilkan keadaan galat
    // sepersekian detik sebelum permintaan asli dari peramban tiba.
    enabled: enabled && Boolean(tenant),
  })
}

/** Bentuk balasan `GET /{tenant}/clinic/reports/monthly`. */

export interface MonthlyRow {
  issued_at: string
  therapist_name?: string | null
  patient_name?: string | null
  invoice_number?: string | null
  payment_label?: string | null
  services: string
  products: string
  treatment_amount: number
  product_amount: number
  total: number
  fee_amount: number
  net_amount: number
}

export interface MonthlyTotals {
  treatment: number
  product: number
  revenue: number
  fee: number
  net: number
}

export interface MonthlySummary {
  visits: number
  patients: number
  new_patients: number
  average_ticket: number
}

export interface MonthlyData {
  from: string
  to: string
  rows: MonthlyRow[]
  totals: MonthlyTotals
  summary: MonthlySummary
  payments: { method: string; method_label: string; total: number }[]
  expenses: {
    by_category: { category: string | null; category_label: string; total: number }[]
    total: number
  }
  commission: {
    rows: { therapist_id: number; therapist_name: string; total: number }[]
    total: number
  }
  net_profit: number
}

/**
 * Warna per blok. Warna hanya menempel pada penanda — batang, titik, latar
 * kepala — dan tidak pernah pada teksnya: warna kategori bawaan design system
 * tidak cukup kontras untuk teks kecil di atas latar terang.
 */
export const BLOCK_ACCENT = {
  revenue: "bg-primary",
  expense: "bg-chart-cat-2",
  fund: "bg-chart-cat-1",
  fee: "bg-chart-cat-4",
} as const

export type BlockAccent = keyof typeof BLOCK_ACCENT

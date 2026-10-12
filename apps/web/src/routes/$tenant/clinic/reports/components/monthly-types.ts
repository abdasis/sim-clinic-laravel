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
  commission_amount: number
  net_amount: number
}

/**
 * Satu staf dalam blok fee. Komisi upselling dipisah dari fee kerja: satu
 * angka gabungan tidak bisa dijelaskan ke terapis yang menanyakannya, dan
 * keterangan targetnya ikut dibawa supaya yang belum tercapai pun bisa
 * menyebut kurang berapa lagi.
 */
export interface CommissionRow {
  therapist_id: number
  therapist_name: string
  revenue: number
  visits: number
  new_patients: number
  fee_amount: number
  commission_amount: number
  /** Ambang yang sedang berlaku untuknya; null bila klinik belum menyetelnya. */
  upsell_target: number | null
  upsell_percent: number | null
  upsell_reached: boolean
  total: number
}

export interface MonthlyTotals {
  treatment: number
  product: number
  revenue: number
  fee: number
  commission: number
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
    rows: CommissionRow[]
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

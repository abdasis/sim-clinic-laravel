import type { PhotoRow } from "#/components/medical-photos/photo-types.ts"
import type { TreatmentRow } from "../../../medical-records/components/medical-record-attachments.tsx"

export interface TransactionItemRow {
  name: string
  kind: string
  unit_price: number
  qty: number
  subtotal: number
}

export interface RecordRow {
  id: number
  created_at?: string | null
  booking?: { id: number; status?: string; start_at?: string | null } | null
  patient_name?: string | null
  author_name?: string | null
  anamnesis?: string | null
  skincare_history?: string | null
  allergy_history?: string | null
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
  transaction?: {
    id: number
    performers: { name: string }[]
    items: TransactionItemRow[]
  } | null
}

export interface PurchaseRow {
  transaction_id: number
  invoice_number?: string | null
  purchased_at?: string | null
  /** Nota yang lahir dari sebuah kunjungan, bukan pembelian yang berdiri sendiri. */
  linked_to_visit: boolean
  items: { name: string; qty: number; unit_price: number; subtotal: number }[]
}

export interface CellLine {
  label: string
  amount?: number
  qty?: number
}

export const DASH = "—"

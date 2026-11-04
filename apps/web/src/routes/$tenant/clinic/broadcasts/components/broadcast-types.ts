/** Pesan broadcast lokal: isi bebas plus pemetaan slot {{n}} ke data penerima. */
export interface BroadcastTemplate {
  body: string
  /** Bisa tidak ada pada data lama; pembaca wajib memakai `?? []`. */
  params?: string[]
}

export type BroadcastAudience = "all" | "inactive" | "service" | "selected"

export interface BroadcastAudienceParams {
  days?: number | null
  service_id?: number | null
  patient_ids?: number[] | null
}

export interface Recipient {
  id: number
  name: string
  phone: string
  message: string
  status: string
  status_label: string
  sent_at?: string | null
  error?: string | null
}

export interface BroadcastDetail {
  id: number
  title: string
  message: string
  status?: string | null
  status_label?: string | null
  paused_reason?: string | null
  resume_after?: string | null
  kind_label?: string | null
  template?: BroadcastTemplate | null
  audience?: BroadcastAudience | null
  audience_label: string
  audience_params?: BroadcastAudienceParams | null
  can_edit?: boolean
  recipients_total: number
  recipients_sent: number
  recipients_pending: number
  recipients_failed: number
  image_url?: string | null
  recipients: Recipient[]
}

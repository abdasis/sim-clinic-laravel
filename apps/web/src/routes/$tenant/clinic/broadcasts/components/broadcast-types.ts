/** Satu template resmi Meta dari endpoint kelola (status apa pun). */
export interface ManagedTemplate {
  id: string | null
  name: string
  language: string
  category: string
  status: string
  rejected_reason: string | null
  body: string
  header_type: "TEXT" | "IMAGE" | null
  header_text: string | null
  body_params_count: number
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
  template_name?: string | null
  template_language?: string | null
  template?: { name: string; language?: string | null } | null
  audience_label: string
  recipients_total: number
  recipients_sent: number
  recipients_pending: number
  recipients_failed: number
  image_url?: string | null
  recipients: Recipient[]
}

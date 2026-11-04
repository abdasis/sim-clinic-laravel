/** Satu klinik pada `GET /central/whatsapp-gateway/clinics`. */
export interface ClinicGatewayRow {
  tenant_id: number
  name: string
  slug: string
  session: string | null
  daily_message_limit: number | null
  api_key_configured: boolean
  webhook_configured: boolean
  webhook_token_set: boolean
  webhook_url?: string | null
}

/** Body `PUT /central/whatsapp-gateway/clinics/{tenantId}`. */
export interface ClinicGatewayPayload {
  api_key: string | null
  session: string | null
  daily_message_limit: number | null
}

export interface GatewaySettings {
  base_url?: string | null
}

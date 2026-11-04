/** Keadaan sambungan dari `GET /broadcasts/connection`. */
export interface ConnectionState {
  available: boolean
  provisioned: boolean
  connected: boolean
  qr: string | null
  number: string | null
  name: string | null
  error: boolean
}

/** Konfigurasi gateway dari `GET /broadcasts/settings`. */
export interface BroadcastSettings {
  gateway_available: boolean
  /** Field baru dari backend; opsional agar halaman tidak menunggu rilis API. */
  api_key_configured?: boolean
  webhook_configured?: boolean
  /** URL webhook milik klinik, null bila webhook belum terdaftar di gateway. */
  webhook_url?: string | null
  gateway_base_url?: string | null
  session?: string | null
  daily_message_limit?: number | null
}

export interface PairingCode {
  pairing_code: string | null
  expires_at: string | null
}

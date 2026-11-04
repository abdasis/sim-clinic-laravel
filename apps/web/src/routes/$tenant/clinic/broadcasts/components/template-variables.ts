import type { BroadcastTemplate } from "./broadcast-types.ts"

/** Variabel penerima yang dikenali backend, urut sesuai `TemplateVariables::KEYS`. */
export const TEMPLATE_VARIABLES = [
  "nama",
  "klinik",
  "klinik_telepon",
  "layanan_terakhir",
  "tanggal_terakhir",
  "hari_sejak_kunjungan",
] as const

/** Batas panjang body, sama dengan aturan `BroadcastRequest` di backend. */
export const BODY_MAX = 4000
export const BODY_MAX_WITH_IMAGE = 1024

const SLOT = /\{\{\s*(\d+)\s*\}\}/g

/** Nomor slot unik di dalam body, urut menaik. */
export function placeholderNumbers(body: string): number[] {
  const found = new Set<number>()

  for (const match of body.matchAll(SLOT)) {
    found.add(Number(match[1]))
  }

  return [...found].sort((left, right) => left - right)
}

/**
 * Selaraskan daftar parameter dengan slot yang sedang ada di body. Pilihan
 * lama dipertahankan per posisi supaya menyunting kalimat tidak menghapus
 * pemetaan yang sudah dibuat.
 */
export function syncParams(body: string, previous: string[] = []): string[] {
  return placeholderNumbers(body).map(
    (_slot, index) => previous[index] ?? TEMPLATE_VARIABLES[index] ?? "",
  )
}

/** Pesan siap kirim: body terisi dan setiap slot punya variabel. */
export function isTemplateComplete(template: BroadcastTemplate | null): boolean {
  if (!template || template.body.trim() === "") return false

  const slots = placeholderNumbers(template.body)
  const params = template.params ?? []

  return params.length === slots.length && params.every((value) => value !== "")
}

export function templatePayload(template: BroadcastTemplate) {
  return { body: template.body, params: template.params ?? [] }
}

/** Alias placeholder lama, sama dengan `TemplateVariables::renderNamed` di backend. */
const LEGACY_ALIASES: Record<string, string> = {
  nama_pasien: "nama",
  nama_treatment: "layanan_terakhir",
  tanggal_treatment: "tanggal_terakhir",
  nomor_clinic: "klinik_telepon",
}

/**
 * Broadcast lama menyimpan badan pesan di kolom `message` dengan placeholder
 * bernama (`{nama}`, `{klinik}`), bukan `template`. Formulir sunting hanya
 * mengenal slot bernomor, jadi ubah nama menjadi `{{n}}` + daftar parameter
 * supaya isinya tidak kosong dan pesan tidak terkirim mentah.
 */
export function templateFromLegacyMessage(
  message: string | null | undefined,
): BroadcastTemplate | null {
  if (!message || message.trim() === "") return null

  const params: string[] = []
  const body = message.replace(
    /\{\{\s*([a-z_]+)\s*\}\}|\{\s*([a-z_]+)\s*\}/gi,
    (match, double: string | undefined, single: string | undefined) => {
      const raw = double ?? single ?? ""
      const key = LEGACY_ALIASES[raw] ?? raw

      if (!(TEMPLATE_VARIABLES as readonly string[]).includes(key)) return match

      let index = params.indexOf(key)

      if (index === -1) {
        params.push(key)
        index = params.length - 1
      }

      return `{{${index + 1}}}`
    },
  )

  return { body, params }
}

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

  return (
    template.params.length === slots.length &&
    template.params.every((value) => value !== "")
  )
}

export function templatePayload(template: BroadcastTemplate) {
  return { body: template.body, params: template.params }
}

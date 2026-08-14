/** Bahasa yang punya berkas terjemahan lengkap di backend. */
export const CONTENT_LOCALES = ["id", "en"] as const

export type ContentLocale = (typeof CONTENT_LOCALES)[number]

export const DEFAULT_LOCALE: ContentLocale = "id"

/** Peta bahasa seperti tersimpan di database: `{ id: …, en: … }`. */
export type Translatable<T = string> = Partial<Record<ContentLocale, T>>

/** Nilai apa pun dari URL dipaksa jadi bahasa yang dikenal. */
export function normalizeLocale(value: unknown): ContentLocale {
  return CONTENT_LOCALES.includes(value as ContentLocale)
    ? (value as ContentLocale)
    : DEFAULT_LOCALE
}

/**
 * Ambil satu bahasa dari peta terjemahan. Jatuh ke bahasa Indonesia bila
 * versi yang diminta belum ditulis — sama seperti di backend, halaman lebih
 * baik tampil berbahasa Indonesia daripada kosong.
 */
export function pickTranslatable<T>(
  value: Translatable<T> | T | null | undefined,
  locale: ContentLocale,
): T | undefined {
  if (value === null || value === undefined) return undefined

  if (typeof value !== "object") return value as T

  const map = value as Translatable<T>

  return map[locale] ?? map[DEFAULT_LOCALE] ?? undefined
}

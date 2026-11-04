import translations from "#/lib/translations-id.json"

export type TranslationGroups = Record<string, Record<string, unknown>>

const store = translations as TranslationGroups

/**
 * Ambil terjemahan berdasarkan key bertitik: t('staff.name').
 * Fallback ke key jika tidak ditemukan (CLAUDE.md i18n).
 */
export function translate(key: string): string {
  const [group, ...rest] = key.split(".")
  let value: unknown = store[group]
  for (const part of rest) {
    if (value && typeof value === "object") {
      value = (value as Record<string, unknown>)[part]
    } else {
      value = undefined
      break
    }
  }
  return typeof value === "string" ? value : key
}

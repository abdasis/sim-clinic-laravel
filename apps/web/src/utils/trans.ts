export type TranslationGroups = Record<string, Record<string, unknown>>

let store: TranslationGroups = {}

export function setTranslations(groups: TranslationGroups) {
  store = groups
}

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

/**
 * Salinan terjemahan di peramban, supaya shell admin tidak menunggu 71KB
 * JSON tiap kali halaman dimuat ulang.
 *
 * Cuma pemercepat tampilan, bukan pengganti pengambilan datanya: tiap kali
 * aplikasi dibuka, versi terbarunya tetap diambil di latar dan salinan ini
 * ditimpa kalau isinya sudah berganti. Jadi salinan basi paling lama bertahan
 * sampai permintaan itu selesai, bukan sampai ada yang membersihkannya.
 */
const CACHE_KEY = "clinic_translations"

interface CachedTranslations {
  locale: string
  version: string
  groups: TranslationGroups
}

/**
 * Semua akses dibungkus try/catch: mode penyamaran, kuota penuh, dan
 * peramban yang memblokir penyimpanan situs sama-sama melempar di sini, dan
 * tidak satu pun layak menjatuhkan seluruh halaman hanya karena pemercepat.
 */
export function readCachedTranslations(locale: string): CachedTranslations | null {
  if (typeof window === "undefined") return null

  try {
    const raw = window.localStorage.getItem(CACHE_KEY)
    if (!raw) return null

    const parsed = JSON.parse(raw) as CachedTranslations

    // Locale berbeda berarti salinan ini milik bahasa lain; membiarkannya
    // terpakai membuat halaman publik tampil dalam bahasa yang salah.
    if (parsed?.locale !== locale || typeof parsed.groups !== "object") return null

    return parsed
  } catch {
    return null
  }
}

export function cacheTranslations(
  locale: string,
  version: string,
  groups: TranslationGroups,
): void {
  if (typeof window === "undefined") return

  try {
    window.localStorage.setItem(
      CACHE_KEY,
      JSON.stringify({ locale, version, groups } satisfies CachedTranslations),
    )
  } catch {
    // Tidak bisa menyimpan berarti kembali ke perilaku lama: menunggu
    // pengambilan tiap kali. Lambat, tapi benar.
  }
}

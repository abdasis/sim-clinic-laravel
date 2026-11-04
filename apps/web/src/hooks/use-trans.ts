import { translate } from "#/utils/trans.ts"
import type { ContentLocale } from "#/lib/company-locale.ts"

/**
 * Kamus Indonesia ikut bundel, jadi tidak ada yang perlu ditunggu.
 * Argumen `locale` dipertahankan supaya pemanggil lama tidak perlu diubah;
 * UI selalu berbahasa Indonesia.
 */
export function useTrans(_locale?: ContentLocale) {
  return {
    t: (key: string) => translate(key),
    isLoading: false,
    ready: true,
  }
}

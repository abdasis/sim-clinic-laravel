import { useCallback, useState } from "react"

import {
  CONTENT_LOCALES,
  DEFAULT_LOCALE,
  type ContentLocale,
  type Translatable,
} from "#/lib/company-locale.ts"

/**
 * Satu field konten punya satu nilai per bahasa. Hook ini menyimpan tab
 * bahasa yang sedang dibuka dan menyediakan pembaca/penulis per bahasa,
 * supaya form cukup mengurus satu nilai gabungan.
 */
export function useTranslatableField() {
  const [locale, setLocale] = useState<ContentLocale>(DEFAULT_LOCALE)

  const read = useCallback(
    <T,>(value: Translatable<T> | null | undefined): T | undefined =>
      value?.[locale],
    [locale],
  )

  const write = useCallback(
    <T,>(
      value: Translatable<T> | null | undefined,
      next: T | null,
    ): Translatable<T> => {
      const merged = { ...(value ?? {}) } as Translatable<T>

      if (next === null || next === undefined || next === "") {
        delete merged[locale]
      } else {
        merged[locale] = next
      }

      return merged
    },
    [locale],
  )

  /** Bahasa yang sudah terisi — dipakai menandai tab di form. */
  const filledLocales = useCallback(
    <T,>(value: Translatable<T> | null | undefined): ContentLocale[] =>
      CONTENT_LOCALES.filter((code) => {
        const entry = value?.[code]

        return entry !== undefined && entry !== null && entry !== ""
      }),
    [],
  )

  return { locale, setLocale, read, write, filledLocales }
}

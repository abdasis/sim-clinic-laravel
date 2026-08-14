import { createContext, useContext, useMemo } from "react"

import {
  DEFAULT_LOCALE,
  pickTranslatable,
  type ContentLocale,
  type Translatable,
} from "#/lib/company-locale.ts"

const CompanyLocaleContext = createContext<ContentLocale>(DEFAULT_LOCALE)

/**
 * Bahasa aktif landing. Konten datang dari API sebagai peta dua bahasa, jadi
 * berganti bahasa cukup mengubah nilai di sini — tidak perlu memuat ulang.
 */
export function CompanyLocaleProvider({
  locale,
  children,
}: {
  locale: ContentLocale
  children: React.ReactNode
}) {
  return (
    <CompanyLocaleContext value={locale}>{children}</CompanyLocaleContext>
  )
}

export function useContentLocale(): ContentLocale {
  return useContext(CompanyLocaleContext)
}

/**
 * Pemilih teks sesuai bahasa aktif. Dipakai sebagai `text(field)` di dalam
 * komponen section.
 */
export function useContentText() {
  const locale = useContentLocale()

  return useMemo(
    () =>
      <T,>(value: Translatable<T> | T | null | undefined): T | undefined =>
        pickTranslatable(value, locale),
    [locale],
  )
}

import { useQuery } from "@tanstack/react-query"
import { apiGet } from "#/lib/api.ts"
import { setTranslations, translate, type TranslationGroups } from "#/utils/trans.ts"
import type { ContentLocale } from "#/lib/company-locale.ts"

/**
 * Muat terjemahan modul klinik dari backend (pengganti share Inertia).
 * Simpan ke store global agar t() dapat dipakai sinkron.
 *
 * `locale` hanya dipakai halaman publik yang punya pengalih bahasa; halaman
 * admin memanggil tanpa argumen dan tetap mengikuti locale default backend.
 */
export function useTrans(locale?: ContentLocale) {
  const { data, isLoading } = useQuery({
    queryKey: ["translations", locale ?? "default"],
    queryFn: async () => {
      const res = await apiGet<{ data: TranslationGroups }>(
        "/translations",
        locale ? { locale } : undefined,
      )
      setTranslations(res.data)
      return res.data
    },
    staleTime: Infinity,
  })

  return {
    t: (key: string) => translate(key),
    isLoading,
    ready: !!data,
  }
}

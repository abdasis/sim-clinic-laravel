import { useQuery } from "@tanstack/react-query"
import { apiGet } from "#/lib/api.ts"
import { setTranslations, translate, type TranslationGroups } from "#/utils/trans.ts"

/**
 * Muat terjemahan modul klinik dari backend (pengganti share Inertia).
 * Simpan ke store global agar t() dapat dipakai sinkron.
 */
export function useTrans() {
  const { data, isLoading } = useQuery({
    queryKey: ["translations"],
    queryFn: async () => {
      const res = await apiGet<{ data: TranslationGroups }>("/translations")
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

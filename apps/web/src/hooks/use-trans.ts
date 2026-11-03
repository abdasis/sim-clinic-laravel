import { useEffect, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import { apiGet } from "#/lib/api.ts"
import {
  cacheTranslations,
  cachedTranslationVersion,
  readCachedTranslations,
  setTranslations,
  translate,
  type TranslationGroups,
} from "#/utils/trans.ts"
import type { ContentLocale } from "#/lib/company-locale.ts"

/**
 * Muat terjemahan modul klinik dari backend (pengganti share Inertia).
 * Simpan ke store global agar t() dapat dipakai sinkron.
 *
 * `locale` hanya dipakai halaman publik yang punya pengalih bahasa; halaman
 * admin memanggil tanpa argumen dan tetap mengikuti locale default backend.
 *
 * Shell admin menahan tampilannya sampai `ready`, jadi menunggu 71KB JSON di
 * sini berarti layar kosong tiap kali halaman dimuat ulang. Salinan di
 * peramban menutup jeda itu: begitu ada, tampilannya langsung berdiri, dan
 * versi terbarunya tetap diambil di latar.
 */
export function useTrans(locale?: ContentLocale) {
  const key = locale ?? "default"
  const [hydrated, setHydrated] = useState(false)

  // Setelah mount, bukan saat render. localStorage tidak ada di server, dan
  // membacanya saat render membuat tampilan server berbeda dari tampilan
  // klien pertama — persis hydration mismatch yang dihindari shell klinik.
  useEffect(() => {
    const cached = readCachedTranslations(key)

    if (cached) {
      setTranslations(cached.groups)
      setHydrated(true)
    }
  }, [key])

  const { data, isLoading } = useQuery({
    queryKey: ["translations", key],
    queryFn: async () => {
      const res = await apiGet<{
        data: TranslationGroups
        meta?: { version?: string }
      }>("/translations", locale ? { locale } : undefined)

      const version = res.meta?.version ?? ""

      setTranslations(res.data)

      // Versinya sidik isi terjemahan dari server. Sama berarti salinan yang
      // sudah ada identik, dan menulisnya ulang cuma memblokir utas utama
      // untuk hasil yang sama persis. Versi kosong berarti server tidak
      // memberi penanda apa pun, jadi tidak ada dasar untuk melewatinya.
      if (version === "" || version !== cachedTranslationVersion(key)) {
        cacheTranslations(key, version, res.data)
      }

      return res.data
    },
    staleTime: Infinity,
  })

  return {
    t: (key: string) => translate(key),
    isLoading,
    // Salinan lokal sudah cukup untuk menggambar layar; pengambilan di latar
    // yang menyusul akan menimpanya kalau ternyata sudah berganti.
    ready: !!data || hydrated,
  }
}

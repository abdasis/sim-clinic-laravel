import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"
import { getAuthUser, setAuthUser } from "#/lib/auth.ts"
import type { AuthUser } from "#/lib/auth.ts"
import { DEFAULT_APPEARANCE, normalizeAppearance } from "#/types/appearance.ts"
import type { Appearance } from "#/types/appearance.ts"

/**
 * Terapkan preferensi ke DOM. Dipakai dua kali: oleh halaman preferensi saat
 * pengguna mencoba-coba pilihan, dan setelah `/me` menyusul dari server.
 *
 * Bentuknya harus sama persis dengan skrip anti-kedip di `__root.tsx` — kalau
 * keduanya berbeda, warnanya akan berkedip sekali setelah hidrasi.
 */
export function applyAppearanceToDom(appearance: Appearance) {
  if (typeof document === "undefined") return

  const root = document.documentElement
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches
  const resolved =
    appearance.mode === "auto" ? (prefersDark ? "dark" : "light") : appearance.mode

  root.classList.remove("light", "dark")
  root.classList.add(resolved)
  root.style.colorScheme = resolved
  root.setAttribute("data-accent", appearance.accent)

  if (appearance.mode === "auto") {
    root.removeAttribute("data-theme")
  } else {
    root.setAttribute("data-theme", appearance.mode)
  }
}

/** Preferensi yang tersimpan di localStorage; bawaan bila belum pernah diatur. */
export function getStoredAppearance(): Appearance {
  return normalizeAppearance(getAuthUser()?.appearance ?? DEFAULT_APPEARANCE)
}

/** Simpan ke localStorage supaya skrip anti-kedip membacanya di muat berikutnya. */
export function persistAppearance(appearance: Appearance) {
  const user = getAuthUser()
  if (!user) return

  setAuthUser({ ...user, appearance })
}

interface MeResponse {
  data: AuthUser
}

/**
 * Sinkron lintas perangkat. Preferensi yang diubah di laptop harus ikut saat
 * membuka klinik dari komputer lain, jadi localStorage disegarkan dari server
 * sekali setiap shell klinik dipasang.
 *
 * Hati-hati: JANGAN menimpa localStorage dengan default saat server null.
 * Appearance null berarti user belum pernah mengatur — localStorage justru
 * sumber kebenaran lokal yang mungkin sudah diisi oleh halaman preferensi.
 */
export function useMe(tenant: string, enabled = true) {
  return useQuery({
    queryKey: ["me", tenant],
    queryFn: async () => {
      const res = await apiGet<MeResponse>(`/${tenant}/me`)

      // Appearance non-null dari server = sumber kebenaran kanonik
      // (sinkron lintas perangkat).
      if (res.data.appearance) {
        const appearance = normalizeAppearance(res.data.appearance)
        setAuthUser({ ...res.data, appearance })
        applyAppearanceToDom(appearance)
      } else {
        // Pertahankan localStorage; hanya sinkron field user lain, bukan
        // appearance.
        setAuthUser({ ...res.data, appearance: getStoredAppearance() })
      }

      return res.data
    },
    enabled: enabled && Boolean(tenant),
    staleTime: 5 * 60_000,
    refetchOnWindowFocus: false,
  })
}

import * as React from "react"

/**
 * `useLayoutEffect` di browser, `useEffect` di server.
 *
 * Dipakai untuk penyesuaian yang harus selesai sebelum browser melukis —
 * memulihkan keadaan sidebar dari cookie, mengukur lebar wadah — supaya
 * bentuk yang salah tidak pernah terlihat sekejap. Di server tidak ada yang
 * dilukis, jadi turun ke `useEffect` agar React tidak memperingatkan
 * `useLayoutEffect` saat render di server.
 */
export const useIsomorphicLayoutEffect =
  typeof window === "undefined" ? React.useEffect : React.useLayoutEffect

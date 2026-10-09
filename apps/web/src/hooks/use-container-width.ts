import * as React from "react"

import { useIsomorphicLayoutEffect } from "#/hooks/use-isomorphic-layout-effect.ts"

/**
 * Lebar wadah, bukan lebar layar.
 *
 * Yang menentukan sebuah tabel masih terbaca bukan ukuran perangkatnya,
 * melainkan ruang yang benar-benar tersisa untuknya. Di tablet 800px ruang itu
 * berubah drastis tergantung sidebar sedang terbuka (~510px) atau diciutkan
 * jadi rel (~710px) — dan lebar layarnya sama saja. Mengukur layar membuat
 * salah satu dari dua keadaan itu selalu salah.
 *
 * Jawabannya `null` selama belum terukur: di server, pada render pertama, dan
 * saat elemennya tersembunyi (lebar 0). `null` berarti "belum tahu", bukan
 * "sangat sempit" — pemanggilnya yang memutuskan bentuk sementaranya.
 */
export function useContainerWidth<T extends HTMLElement>() {
  const ref = React.useRef<T>(null)
  const [width, setWidth] = React.useState<number | null>(null)

  useIsomorphicLayoutEffect(() => {
    const element = ref.current

    if (!element || typeof ResizeObserver === "undefined") return

    const read = () => {
      const measured = element.getBoundingClientRect().width

      setWidth(measured > 0 ? measured : null)
    }

    const observer = new ResizeObserver(read)
    observer.observe(element)
    read()

    return () => observer.disconnect()
  }, [])

  return [ref, width] as const
}

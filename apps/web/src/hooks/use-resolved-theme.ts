import { useEffect, useState } from "react"

/**
 * Tema yang benar-benar sedang tampil, dibaca dari kelas `dark` di elemen akar.
 *
 * Sengaja tidak memakai `prefers-color-scheme`: pengguna bisa memilih terang
 * padahal perangkatnya gelap, dan pilihan itu yang harus diikuti. Kelas akar
 * sudah merangkum ketiganya — terang, gelap, dan ikut perangkat — karena skrip
 * pra-cat dan halaman preferensi sama-sama menuliskannya ke sana.
 */
export function useResolvedTheme(): "light" | "dark" {
  const [theme, setTheme] = useState<"light" | "dark">("light")

  useEffect(() => {
    const read = () =>
      setTheme(
        document.documentElement.classList.contains("dark") ? "dark" : "light",
      )

    read()

    const observer = new MutationObserver(read)
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class"],
    })

    return () => observer.disconnect()
  }, [])

  return theme
}

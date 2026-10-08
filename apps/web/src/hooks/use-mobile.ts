import * as React from "react"

/**
 * Tiga tier layout admin, dari satu sumber ambang.
 *
 * Sebelumnya hanya ada dua mode — mobile dan desktop, dipisah di 768px —
 * sehingga iPad portrait (768-820px) diperlakukan persis seperti layar lebar:
 * sidebar 16rem permanen menyisakan konten ~496px untuk tabel berkolom enam.
 * Kasir sudah menyiasatinya dengan ambang sendiri (1024px), jadi POS punya
 * "tier tablet" sementara halaman lain tidak.
 *
 * Yang ditaruh di sini ambangnya saja. Apa yang dilakukan tiap tier adalah
 * keputusan shell, bukan keputusan hook.
 */
export type LayoutTier = "mobile" | "tablet" | "desktop"

/** Batas bawah tablet: iPad portrait terkecil. */
export const TABLET_MIN_WIDTH = 768

/** Batas bawah desktop: iPad landscape sudah cukup lebar untuk sidebar penuh. */
export const DESKTOP_MIN_WIDTH = 1024

export function tierForWidth(width: number): LayoutTier {
  if (width < TABLET_MIN_WIDTH) return "mobile"
  if (width < DESKTOP_MIN_WIDTH) return "tablet"

  return "desktop"
}

/**
 * Tier yang sedang berlaku.
 *
 * Nilai awalnya `desktop` di server dan pada render pertama: kerangka layar
 * (ShellSkeleton) memakai tier yang sama, jadi yang tampil sebelum ukuran
 * terbaca adalah bentuk desktop — bukan bentuk mobile yang lalu melompat.
 */
export function useLayoutTier(): LayoutTier {
  const [tier, setTier] = React.useState<LayoutTier>("desktop")

  React.useEffect(() => {
    const read = () => setTier(tierForWidth(window.innerWidth))

    // Dua ambang, satu pendengar: `matchMedia` menyalakan `change` saat
    // melewati salah satunya, dan lebarnya dibaca ulang dari window supaya
    // tidak ada dua sumber kebenaran.
    const queries = [
      window.matchMedia(`(min-width: ${TABLET_MIN_WIDTH}px)`),
      window.matchMedia(`(min-width: ${DESKTOP_MIN_WIDTH}px)`),
    ]

    queries.forEach((q) => q.addEventListener("change", read))
    read()

    return () => queries.forEach((q) => q.removeEventListener("change", read))
  }, [])

  return tier
}

/**
 * Turunan dari tier, bukan ambang kedua: mobile adalah tier paling sempit.
 *
 * Dipertahankan sebagai nama tersendiri karena itu yang dibaca `ui/sidebar.tsx`
 * dan komponen pihak ketiga lain yang menyalin pola shadcn.
 */
export function useIsMobile() {
  return useLayoutTier() === "mobile"
}

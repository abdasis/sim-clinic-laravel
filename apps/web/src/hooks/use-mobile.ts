import * as React from "react"

/**
 * Empat tier layout admin, dari satu sumber ambang.
 *
 * Yang membedakannya bukan jenis perangkat melainkan apa yang muat:
 *
 * - `mobile` — tidak ada ruang untuk sidebar apa pun; navigasinya laci.
 * - `rail` — cukup untuk rel ikon permanen, belum cukup untuk sidebar
 *   bernama. Di sinilah tablet kecil berada: Galaxy Tab A potret melaporkan
 *   600px, sebagian model malah 533px.
 * - `tablet` — sidebar penuh muat, tapi kontennya masih sempit, jadi rel ikon
 *   tetap disediakan bagi yang mau menukarnya.
 * - `desktop` — sidebar penuh tanpa kompromi.
 *
 * Yang ditaruh di sini ambangnya saja. Apa yang dilakukan tiap tier adalah
 * keputusan shell, bukan keputusan hook.
 */
export type LayoutTier = "mobile" | "rail" | "tablet" | "desktop"

/**
 * Ambangnya ditulis dalam rem, bukan px, karena itu persis yang dipakai
 * Tailwind — dan sidebar menyembunyikan dirinya lewat varian `rail:`
 * (`--breakpoint-rail` di `styles.css`), bukan lewat tier ini.
 *
 * Kalau JS mengukur sendiri dengan `window.innerWidth`, keduanya bisa berbeda
 * pendapat: `innerWidth` ikut menghitung lebar scrollbar klasik (~15px),
 * sementara media query tidak. Di jendela tepat di atas ambang, JS membaca
 * "sudah lewat" padahal CSS belum — dan di selisih itu sidebar desktopnya
 * tetap tersembunyi sementara lacinya sudah tidak dipasang. Hasilnya pita
 * sempit tanpa sidebar sama sekali. Karena itu tier dibaca dari media query
 * yang sama dengan CSS-nya.
 */
const RAIL_QUERY = "(min-width: 32rem)"
const TABLET_QUERY = "(min-width: 48rem)"
const DESKTOP_QUERY = "(min-width: 64rem)"

/** Padanan piksel ambang di atas pada ukuran font root bawaan (16px). */
export const RAIL_MIN_WIDTH = 512
export const TABLET_MIN_WIDTH = 768
export const DESKTOP_MIN_WIDTH = 1024

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
    const queries = [RAIL_QUERY, TABLET_QUERY, DESKTOP_QUERY].map((query) =>
      window.matchMedia(query),
    )
    const [rail, tablet, desktop] = queries

    const read = () =>
      setTier(
        desktop.matches
          ? "desktop"
          : tablet.matches
            ? "tablet"
            : rail.matches
              ? "rail"
              : "mobile",
      )

    queries.forEach((query) => query.addEventListener("change", read))
    read()

    return () =>
      queries.forEach((query) => query.removeEventListener("change", read))
  }, [])

  return tier
}

/**
 * Tier yang sidebarnya berdiri sendiri di samping konten, bukan laci yang
 * menimpanya. Dipakai shell untuk memutuskan bentuk ciutnya: rel ikon, bukan
 * menghilang ke luar layar.
 */
export function hasRail(tier: LayoutTier): boolean {
  return tier === "rail" || tier === "tablet"
}

/**
 * Layar yang terlalu sempit untuk tata letak lebar, apa pun bentuk
 * sidebarnya: ponsel dan tablet kecil. Dipakai untuk keputusan yang hanya
 * soal sempitnya ruang — breadcrumb yang dilipat, tabel yang jadi kartu
 * sebelum wadahnya sempat diukur.
 */
export function isNarrowTier(tier: LayoutTier): boolean {
  return tier === "mobile" || tier === "rail"
}

/**
 * Turunan dari tier, bukan ambang kedua: mobile adalah tier paling sempit.
 *
 * Dipertahankan sebagai nama tersendiri karena itu nama yang dipakai pola
 * shadcn, dan komponen pihak ketiga yang disalin ke sini mencarinya.
 */
export function useIsMobile(): boolean {
  return useLayoutTier() === "mobile"
}

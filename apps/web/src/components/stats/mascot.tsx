/**
 * Maskot untuk banner ajakan di kepala halaman index.
 *
 * ponytail: digambar inline, bukan berkas di `public/`. Aksennya memakai
 * `currentColor` dan token `--lagoon`/`--sea-ink`, dan keduanya tidak ikut
 * terbaca kalau SVG dimuat lewat `<img>` — jadi maskotnya akan mati warna di
 * mode gelap. Pindahkan ke berkas statis bila kelak warnanya dibekukan.
 */
export type MascotVariant = "clinic" | "platform"

interface MascotProps {
  variant?: MascotVariant
  className?: string
}

export function Mascot({ variant = "clinic", className }: MascotProps) {
  return variant === "platform" ? (
    <PlatformMascot className={className} />
  ) : (
    <ClinicMascot className={className} />
  )
}

/** Pebble laut berdaun yang memeluk papan catatan — ramah, tidak kekanakan. */
function ClinicMascot({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 120 120" role="img" aria-hidden="true" className={className}>
      {/* Bayangan tipis supaya karakternya terasa berpijak. */}
      <ellipse cx="60" cy="104" rx="30" ry="5" fill="currentColor" opacity="0.14" />

      {/* Badan: pebble membulat, bukan lingkaran sempurna. */}
      <path
        d="M60 26c21 0 34 16 34 37 0 24-15 38-34 38S26 87 26 63c0-21 13-37 34-37z"
        fill="currentColor"
        opacity="0.2"
      />
      <path
        d="M60 26c21 0 34 16 34 37 0 24-15 38-34 38S26 87 26 63c0-21 13-37 34-37z"
        fill="none"
        stroke="currentColor"
        strokeOpacity="0.5"
        strokeWidth="2"
      />

      {/* Sepasang daun di ubun-ubun. */}
      <path
        d="M60 26c-8 0-13-5-13-12 7 0 12 4 13 10 1-6 6-10 13-10 0 7-5 12-13 12z"
        fill="var(--palm)"
        fillOpacity="0.8"
      />

      {/* Papan catatan yang dipeluk — isyarat "data" tanpa ikon grafik kaku. */}
      <rect
        x="42"
        y="66"
        width="36"
        height="28"
        rx="5"
        fill="var(--card)"
        stroke="currentColor"
        strokeOpacity="0.45"
        strokeWidth="2"
      />
      <path
        d="M48 86l7-8 6 5 9-12"
        fill="none"
        stroke="currentColor"
        strokeOpacity="0.85"
        strokeWidth="2.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />

      {/* Wajah. */}
      <circle cx="50" cy="53" r="3.4" fill="var(--sea-ink)" />
      <circle cx="70" cy="53" r="3.4" fill="var(--sea-ink)" />
      <path
        d="M52 61c2 3 5 4.5 8 4.5s6-1.5 8-4.5"
        fill="none"
        stroke="var(--sea-ink)"
        strokeWidth="2.4"
        strokeLinecap="round"
      />
      <ellipse cx="41" cy="59" rx="4" ry="2.6" fill="currentColor" opacity="0.45" />
      <ellipse cx="79" cy="59" rx="4" ry="2.6" fill="currentColor" opacity="0.45" />
    </svg>
  )
}

/** Mercusuar di pulau kecil — kiasan untuk memantau banyak klinik sekaligus. */
function PlatformMascot({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 120 120" role="img" aria-hidden="true" className={className}>
      <ellipse cx="60" cy="102" rx="34" ry="6" fill="currentColor" opacity="0.14" />

      {/* Pulau. */}
      <path
        d="M24 100c6-10 18-14 36-14s30 4 36 14z"
        fill="var(--palm)"
        fillOpacity="0.35"
      />

      {/* Menara. */}
      <path
        d="M50 86l4-44h12l4 44z"
        fill="currentColor"
        opacity="0.2"
      />
      <path
        d="M50 86l4-44h12l4 44z"
        fill="none"
        stroke="currentColor"
        strokeOpacity="0.5"
        strokeWidth="2"
      />
      <rect x="52" y="60" width="16" height="8" fill="currentColor" opacity="0.35" />

      {/* Rumah lampu dan sorotnya. */}
      <rect
        x="51"
        y="30"
        width="18"
        height="12"
        rx="3"
        fill="var(--card)"
        stroke="currentColor"
        strokeOpacity="0.5"
        strokeWidth="2"
      />
      <circle cx="60" cy="36" r="3" fill="currentColor" opacity="0.9" />
      <path d="M69 33l16-6M69 39l16 6" stroke="currentColor" strokeOpacity="0.35" strokeWidth="2.4" strokeLinecap="round" />
      <path d="M51 33l-16-6M51 39l-16 6" stroke="currentColor" strokeOpacity="0.35" strokeWidth="2.4" strokeLinecap="round" />
      <path d="M60 30v-7" stroke="currentColor" strokeOpacity="0.5" strokeWidth="2.4" strokeLinecap="round" />
    </svg>
  )
}

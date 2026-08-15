/**
 * Maskot klinik: wajah ramah dengan daun kecil, digambar sebagai SVG inline
 * memakai token brand supaya ikut berganti di mode gelap tanpa aset kedua.
 */
export function Mascot({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 96 96"
      role="img"
      aria-hidden="true"
      className={className}
    >
      <defs>
        <linearGradient id="mascot-face" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--lagoon)" stopOpacity="0.32" />
          <stop offset="100%" stopColor="var(--lagoon-deep)" stopOpacity="0.5" />
        </linearGradient>
      </defs>

      {/* Lingkaran latar, sengaja lembut supaya tidak bersaing dengan teks. */}
      <circle cx="48" cy="52" r="34" fill="url(#mascot-face)" />
      <circle
        cx="48"
        cy="52"
        r="34"
        fill="none"
        stroke="var(--lagoon-deep)"
        strokeOpacity="0.35"
        strokeWidth="1.5"
      />

      {/* Dua daun di ubun-ubun. */}
      <path
        d="M48 20c-7 0-12-5-12-11 6 0 11 4 12 9 1-5 6-9 12-9 0 6-5 11-12 11z"
        fill="var(--palm)"
        fillOpacity="0.75"
      />
      <path
        d="M48 20v-8"
        stroke="var(--palm)"
        strokeOpacity="0.75"
        strokeWidth="2"
        strokeLinecap="round"
      />

      {/* Mata dan senyum. */}
      <circle cx="38" cy="48" r="3.2" fill="var(--sea-ink)" />
      <circle cx="58" cy="48" r="3.2" fill="var(--sea-ink)" />
      <path
        d="M38 60c3 4 7 6 10 6s7-2 10-6"
        fill="none"
        stroke="var(--sea-ink)"
        strokeWidth="2.4"
        strokeLinecap="round"
      />

      {/* Rona pipi. */}
      <ellipse cx="31" cy="56" rx="4" ry="2.6" fill="var(--lagoon)" fillOpacity="0.45" />
      <ellipse cx="65" cy="56" rx="4" ry="2.6" fill="var(--lagoon)" fillOpacity="0.45" />
    </svg>
  )
}

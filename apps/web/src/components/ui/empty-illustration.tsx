import { motion, useReducedMotion, type Variants } from "motion/react"

import { cn } from "#/lib/utils.ts"

/**
 * Ilustrasi untuk keadaan kosong. Digambar inline dengan `currentColor` dan
 * stroke tipis supaya ikut warna teks sekitarnya di mode terang maupun gelap,
 * dan beranimasi begitu kartu `Empty` di atasnya disentuh kursor.
 *
 * Tiap gambar dibatasi satu bentuk utama plus satu aksen. Bagian yang bergerak
 * adalah aksennya, bukan seluruh gambar — yang penuh gerak justru sulit dibaca.
 */
export type EmptyIllustrationName =
  | "default"
  | "bookings"
  | "patients"
  | "products"
  | "pos"
  | "medical-records"
  | "staff"
  | "users"
  | "tenants"
  | "inventory-select"
  | "reports"
  | "cart"
  | "company-profile"
  | "payments"
  | "history-clock"

type Motion = "float" | "draw" | "pulse"

/**
 * `rest` dan `hover` diwarisi dari root `Empty`, jadi menyentuh bagian mana pun
 * dari kartu sudah cukup untuk menghidupkan gambarnya.
 */
const VARIANTS: Record<Motion, Variants> = {
  float: {
    rest: { y: 0 },
    hover: { y: -4, transition: { type: "spring", stiffness: 280, damping: 14 } },
  },
  draw: {
    rest: { pathLength: 1, opacity: 1 },
    hover: {
      pathLength: [0, 1],
      opacity: [0.35, 1],
      transition: { duration: 0.9, ease: "easeInOut" },
    },
  },
  pulse: {
    rest: { opacity: 0.55, scale: 1 },
    hover: {
      opacity: [0.55, 0.9, 0.55],
      scale: [1, 1.06, 1],
      transition: { duration: 1.4, repeat: Infinity, ease: "easeInOut" },
    },
  },
}

interface EmptyIllustrationProps {
  name?: EmptyIllustrationName
  className?: string
}

export function EmptyIllustration({
  name = "default",
  className,
}: EmptyIllustrationProps) {
  // Saat pengguna meminta gerak seminimal mungkin, gambarnya tetap utuh —
  // hanya animasinya yang dilepas.
  const still = Boolean(useReducedMotion())

  return (
    <svg
      viewBox="0 0 120 120"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.5}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn("size-20 text-muted-foreground/60", className)}
    >
      <Art name={name} still={still} />
    </svg>
  )
}

/** Bagian yang bergerak; jadi elemen biasa saat animasinya dimatikan. */
function Move({
  as = "g",
  kind,
  still,
  ...props
}: {
  as?: "g" | "path" | "rect"
  kind: Motion
  still: boolean
  [key: string]: unknown
}) {
  const Component = motion[as]

  return <Component variants={still ? undefined : VARIANTS[kind]} {...props} />
}

function Art({ name, still }: { name: EmptyIllustrationName; still: boolean }) {
  switch (name) {
    case "bookings":
      return (
        <>
          <rect x="28" y="36" width="64" height="56" rx="8" />
          <path d="M28 52h64M44 28v12M76 28v12" />
          <Move as="path" kind="draw" still={still} d="M48 70l8 8 16-16" />
        </>
      )

    case "patients":
      return (
        <Move kind="float" still={still}>
          <circle cx="50" cy="48" r="11" />
          <path d="M30 88a20 20 0 0 1 40 0" />
          <circle cx="80" cy="54" r="8" />
          <path d="M68 88a14 14 0 0 1 26-2" />
        </Move>
      )

    case "products":
      return (
        <>
          <path d="M60 26l30 16v34L60 92 30 76V42z" />
          <Move as="path" kind="draw" still={still} d="M30 42l30 16 30-16M60 58v34" />
        </>
      )

    case "pos":
      return (
        <>
          <path d="M36 26h48v70l-8-6-8 6-8-6-8 6-8-6-8 6z" />
          <Move as="path" kind="draw" still={still} d="M48 46h24M48 58h24M48 70h14" />
        </>
      )

    case "medical-records":
      return (
        <Move kind="float" still={still}>
          <rect x="30" y="32" width="60" height="62" rx="8" />
          <path d="M48 26h24a4 4 0 0 1 4 4v6H44v-6a4 4 0 0 1 4-4z" />
          <path d="M44 58h32M44 72h20" />
        </Move>
      )

    case "staff":
      return (
        <Move kind="float" still={still}>
          <circle cx="60" cy="44" r="10" />
          <path d="M42 82a18 18 0 0 1 36 0" />
          <circle cx="34" cy="58" r="7" />
          <path d="M22 84a12 12 0 0 1 13-10" />
          <circle cx="86" cy="58" r="7" />
          <path d="M98 84a12 12 0 0 0-13-10" />
        </Move>
      )

    case "users":
      return (
        <>
          <rect x="24" y="38" width="58" height="40" rx="6" />
          <path d="M24 46l29 18 29-18" />
          <Move as="path" kind="pulse" still={still} d="M88 68v20M78 78h20" />
        </>
      )

    case "tenants":
      return (
        <>
          <path d="M34 94V34a6 6 0 0 1 6-6h28a6 6 0 0 1 6 6v60M74 94V54h12a6 6 0 0 1 6 6v34M26 94h68" />
          <Move
            as="path"
            kind="draw"
            still={still}
            d="M46 44h7M61 44h7M46 58h7M61 58h7M46 72h7M61 72h7"
          />
        </>
      )

    case "inventory-select":
      return (
        <>
          <rect x="54" y="26" width="42" height="34" rx="6" />
          <Move kind="float" still={still}>
            <path d="M26 94V62a12 12 0 0 1 12-12h24" />
            <path d="M54 42l8 8-8 8" />
          </Move>
        </>
      )

    case "reports":
      return (
        <>
          <path d="M30 26v66h62" />
          <Move
            as="path"
            kind="draw"
            still={still}
            d="M44 92V70M60 92V52M76 92V62M90 92V38"
          />
        </>
      )

    case "cart":
      return (
        <Move kind="float" still={still}>
          <path d="M24 32h10l9 40h40l8-28H40" />
          <circle cx="50" cy="86" r="5" />
          <circle cx="80" cy="86" r="5" />
        </Move>
      )

    case "company-profile":
      return (
        <>
          <rect x="28" y="30" width="28" height="28" rx="5" />
          <rect x="64" y="30" width="28" height="28" rx="5" />
          <rect x="28" y="66" width="28" height="28" rx="5" />
          <Move
            as="rect"
            kind="pulse"
            still={still}
            x="64"
            y="66"
            width="28"
            height="28"
            rx="5"
            style={{ transformOrigin: "78px 80px" }}
          />
        </>
      )

    case "payments":
      return (
        <Move kind="float" still={still}>
          <path d="M22 46a8 8 0 0 1 8-8h46a8 8 0 0 1 8 8v36a8 8 0 0 1-8 8H30a8 8 0 0 1-8-8z" />
          <path d="M84 56h10a4 4 0 0 1 4 4v9a4 4 0 0 1-4 4H84z" />
          <path d="M89 64v1" />
        </Move>
      )

    case "history-clock":
      return (
        <>
          <circle cx="60" cy="60" r="30" />
          <Move as="path" kind="draw" still={still} d="M60 40v20l15 9" />
        </>
      )

    default:
      return (
        <>
          <path d="M26 46a6 6 0 0 1 6-6h20l7 8h29a6 6 0 0 1 6 6v30a6 6 0 0 1-6 6H32a6 6 0 0 1-6-6z" />
          <Move as="path" kind="draw" still={still} d="M44 66h32M44 78h20" />
        </>
      )
  }
}

/** Cocok dengan App\Enums\AppearanceOption di backend. */
export type AccentKey =
  | "teal"
  | "neutral"
  | "gray"
  | "zinc"
  | "slate"
  | "stone"
  | "blue"
  | "violet"
  | "orange"
  | "green"
  | "rose"
  | "red"
  | "yellow"
  | "gold"

export type ThemeMode = "light" | "dark" | "auto"

export type SidebarVariant = "sidebar" | "floating" | "inset"

export interface Appearance {
  accent: AccentKey
  mode: ThemeMode
  sidebar_variant: SidebarVariant
}

export const DEFAULT_APPEARANCE: Appearance = {
  accent: "teal",
  mode: "auto",
  sidebar_variant: "inset",
}

/**
 * Warna contoh untuk kotak pilihan. Sengaja nilai terang tiap accent supaya
 * kotaknya terbaca di atas latar kartu, bukan token `--primary` yang ikut
 * berubah saat accent-nya sedang aktif.
 */
export const ACCENTS: Array<{ key: AccentKey; swatch: string }> = [
  { key: "teal", swatch: "oklch(0.596 0.145 163.225)" },
  { key: "neutral", swatch: "oklch(0.205 0 0)" },
  { key: "gray", swatch: "oklch(0.21 0.034 264.665)" },
  { key: "zinc", swatch: "oklch(0.21 0.006 285.885)" },
  { key: "slate", swatch: "oklch(0.208 0.042 265.755)" },
  { key: "stone", swatch: "oklch(0.216 0.006 56.043)" },
  { key: "blue", swatch: "oklch(0.546 0.245 262.881)" },
  { key: "violet", swatch: "oklch(0.541 0.281 293.009)" },
  { key: "orange", swatch: "oklch(0.646 0.222 41.116)" },
  { key: "green", swatch: "oklch(0.627 0.194 149.214)" },
  { key: "rose", swatch: "oklch(0.586 0.253 17.585)" },
  { key: "red", swatch: "oklch(0.577 0.245 27.325)" },
  { key: "yellow", swatch: "oklch(0.681 0.162 75.834)" },
  { key: "gold", swatch: "oklch(0.818 0.12 87.9)" },
]

export const MODES: ThemeMode[] = ["light", "dark", "auto"]

export const SIDEBAR_VARIANTS: SidebarVariant[] = ["sidebar", "floating", "inset"]

/** Nilai yang tidak dikenal (mis. sisa versi lama) dikembalikan ke bawaan. */
export function normalizeAppearance(value: unknown): Appearance {
  const raw = (value ?? {}) as Partial<Appearance>
  const accents = ACCENTS.map((a) => a.key)

  return {
    accent: accents.includes(raw.accent as AccentKey)
      ? (raw.accent as AccentKey)
      : DEFAULT_APPEARANCE.accent,
    mode: MODES.includes(raw.mode as ThemeMode)
      ? (raw.mode as ThemeMode)
      : DEFAULT_APPEARANCE.mode,
    sidebar_variant: SIDEBAR_VARIANTS.includes(raw.sidebar_variant as SidebarVariant)
      ? (raw.sidebar_variant as SidebarVariant)
      : DEFAULT_APPEARANCE.sidebar_variant,
  }
}

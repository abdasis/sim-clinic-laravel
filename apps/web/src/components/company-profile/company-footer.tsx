import { HugeiconsIcon } from "@hugeicons/react"
import { Call02Icon, Clock01Icon, Location01Icon } from "@hugeicons/core-free-icons"

import type {
  CompanyNavItem,
  CompanySettings,
  OperatingHour,
} from "#/hooks/use-company-profile.ts"
import { internalHref } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"
import { SocialLinks } from "./social-links.tsx"

interface FooterLabels {
  explore: string
  social: string
  contact: string
  hours: string
  closed: string
}

interface CompanyFooterProps {
  tenant: string
  settings: CompanySettings | null
  items: CompanyNavItem[]
  labels: FooterLabels
}

export function CompanyFooter({
  tenant,
  settings,
  items,
  labels,
}: CompanyFooterProps) {
  const text = useContentText()
  const brand = text(settings?.site_name) ?? tenant
  const socials = settings?.social_links ?? []
  const hours = summarizeHours(settings?.operating_hours ?? null, labels.closed)

  return (
    <footer className="border-t border-border/50 bg-muted/30">
      {/* Kolom footer diberi kepala. Tanpa label, tiga kolom tautan di bawah
          terbaca sebagai satu daftar panjang yang terpotong -- pengunjung
          tidak tahu mana menu, mana kontak, mana jam buka. */}
      <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1.2fr_1fr]">
        <div className="space-y-3">
          <div className="flex items-center gap-2.5">
            {settings?.logo_url ? (
              <img
                src={settings.logo_url}
                alt=""
                className="size-9 rounded-lg object-contain"
              />
            ) : null}
            <div className="min-w-0">
              <p className="text-base leading-tight font-semibold tracking-tight">
                {brand}
              </p>
              {settings?.tagline ? (
                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                  {settings.tagline}
                </p>
              ) : null}
            </div>
          </div>

          {settings?.copyright_text ? (
            <p className="max-w-xs text-sm leading-relaxed text-pretty text-muted-foreground">
              {settings.copyright_text}
            </p>
          ) : null}

          {/* Ikon sosial duduk di blok merek, bukan jadi kolom daftar teks:
              lambangnya sudah dikenali tanpa perlu dibaca. */}
          <SocialLinks links={socials} className="pt-1" />
        </div>

        {items.length > 0 ? (
          <FooterColumn label={labels.explore}>
            <nav className="flex flex-col gap-2">
              {items.map((item) => (
                <FooterLink key={item.id} tenant={tenant} item={item} />
              ))}
            </nav>
          </FooterColumn>
        ) : null}

        {settings?.address || settings?.phone ? (
          <FooterColumn label={labels.contact}>
            <ul className="space-y-2.5 text-sm text-muted-foreground">
              {settings.address ? (
                <li className="flex gap-2">
                  <HugeiconsIcon
                    icon={Location01Icon}
                    strokeWidth={1.8}
                    className="mt-0.5 size-4 shrink-0 text-foreground/40"
                  />
                  <span className="leading-relaxed text-pretty">
                    {settings.address}
                  </span>
                </li>
              ) : null}
              {settings.phone ? (
                <li className="flex gap-2">
                  <HugeiconsIcon
                    icon={Call02Icon}
                    strokeWidth={1.8}
                    className="mt-0.5 size-4 shrink-0 text-foreground/40"
                  />
                  <a
                    href={`tel:${settings.phone.replace(/[^\d+]/g, "")}`}
                    className="tabular-nums transition-colors hover:text-foreground"
                  >
                    {settings.phone}
                  </a>
                </li>
              ) : null}
            </ul>
          </FooterColumn>
        ) : null}

        {hours.length > 0 ? (
          <FooterColumn label={labels.hours}>
            <ul className="space-y-1.5 text-sm text-muted-foreground">
              {hours.map((row) => (
                <li
                  key={row.label}
                  className="flex items-baseline justify-between gap-3"
                >
                  <span>{row.label}</span>
                  <span className="shrink-0 tabular-nums">{row.value}</span>
                </li>
              ))}
            </ul>
            <p className="flex items-center gap-1.5 pt-1 text-xs text-muted-foreground/80">
              <HugeiconsIcon
                icon={Clock01Icon}
                strokeWidth={1.8}
                className="size-3.5"
              />
              WIB
            </p>
          </FooterColumn>
        ) : null}
      </div>

      <div className="border-t border-border/50">
        <p className="mx-auto w-full max-w-6xl px-4 py-4 text-xs text-muted-foreground">
          &copy; {new Date().getFullYear()} {settings?.copyright_text ?? brand}
        </p>
      </div>
    </footer>
  )
}

function FooterColumn({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="space-y-3">
      <p className="text-2xs font-semibold tracking-[0.16em] text-foreground/60 uppercase">
        {label}
      </p>
      {children}
    </div>
  )
}

function FooterLink({ tenant, item }: { tenant: string; item: CompanyNavItem }) {
  const text = useContentText()

  if (!item.url) return null

  const href =
    item.link_type === "route_internal"
      ? internalHref(tenant, item.url)
      : item.url

  return (
    <a
      href={href}
      className="text-sm text-muted-foreground transition-colors hover:text-foreground"
      {...(item.link_type === "external"
        ? { target: "_blank", rel: "noreferrer noopener" }
        : {})}
    >
      {text(item.label)}
    </a>
  )
}

/** Urutan hari beserta singkatannya; kunci mengikuti data setelan klinik. */
const DAYS: Array<[key: string, label: string]> = [
  ["monday", "Sen"],
  ["tuesday", "Sel"],
  ["wednesday", "Rab"],
  ["thursday", "Kam"],
  ["friday", "Jum"],
  ["saturday", "Sab"],
  ["sunday", "Min"],
]

/**
 * Ringkas jam buka jadi beberapa baris rentang.
 *
 * Tujuh baris jam yang isinya sama persis membuat pengunjung membaca tabel
 * alih-alih menangkap satu fakta. Hari berurutan yang jamnya sama digabung —
 * "Sen–Sab 09.00–20.00" terbaca sekali lihat, dan hari yang menyimpang tetap
 * berdiri sendiri karena justru itu yang perlu diingat.
 */
function summarizeHours(
  hours: Record<string, OperatingHour> | null,
  closedLabel: string,
): Array<{ label: string; value: string }> {
  if (!hours) return []

  const rows: Array<{ label: string; value: string }> = []
  let start: string | null = null
  let end: string | null = null
  let current: string | null = null

  const flush = () => {
    if (start === null || current === null) return

    rows.push({ label: start === end ? start : `${start}–${end}`, value: current })
  }

  for (const [key, label] of DAYS) {
    const day = hours[key]
    const value =
      day?.is_open && day.open && day.close
        ? `${day.open.replace(":", ".")}–${day.close.replace(":", ".")}`
        : closedLabel

    if (value === current) {
      end = label
      continue
    }

    flush()
    start = label
    end = label
    current = value
  }

  flush()

  return rows
}

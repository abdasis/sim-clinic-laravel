import { Link } from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import { ArrowLeft01Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"

/** Tombol kirim halaman masuk: selebar form, terangkat sedikit saat disentuh,
 *  mengecil halus saat ditekan. */
export const AUTH_SUBMIT_CLASS =
  "w-full transition-transform duration-150 ease-out hover:-translate-y-px active:scale-[0.96]"

export interface AuthValuePoint {
  icon: IconSvgElement
  label: string
}

interface AuthLayoutProps {
  kicker: string
  title: string
  subtitle?: string
  points: AuthValuePoint[]
  formHeading: string
  formSubheading?: string
  /** `text` mendahului tautan, mis. "Belum punya akun?" + "Daftar". */
  footerLink?: { text: string; linkLabel: string; to: AuthFooterTarget }
  children: React.ReactNode
}

/** Hanya dua tujuan yang masuk akal dari halaman masuk. */
type AuthFooterTarget = "/register" | "/central/login"

const FOCUS_RING =
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background"

/**
 * Kerangka halaman masuk dua kolom minimalis: panel brand di kiri, form di
 * kanan. Dipakai tiga route yang tidak sekerabat, jadi tinggal di components/
 * global — bukan dikolokasi ke salah satu subtree.
 *
 * ponytail: bersih ala Linear — tipografi sebagai hierarki, bukan ornamen.
 * Panel kiri bg solid + border-r tipis sebagai pemisah; form kanan mengambang
 * di whitespace tanpa wadah. Tidak ada pattern, glow, atau kartu. Bungkus
 * form dengan Card bila kelak butuh batas tegas karena formnya padat.
 */
export function AuthLayout({
  kicker,
  title,
  subtitle,
  points,
  formHeading,
  formSubheading,
  footerLink,
  children,
}: AuthLayoutProps) {
  const { t } = useTrans()

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <BrandPanel
        kicker={kicker}
        title={title}
        subtitle={subtitle}
        points={points}
      />

      <div className="flex flex-col items-center justify-center px-4 py-12 lg:px-14">
        {/* Di layar sempit panel brand disembunyikan; kickernya tetap ada
            supaya halaman tidak kehilangan konteks sama sekali. */}
        <p className="island-kicker rise-in mb-6 lg:hidden" style={{ animationDelay: "0ms" }}>
          {kicker}
        </p>

        <div className="w-full max-w-sm">
          <h2
            className="display-title rise-in text-2xl font-bold tracking-tight text-balance text-sea-ink"
            style={{ animationDelay: "60ms" }}
          >
            {formHeading}
          </h2>
          {formSubheading ? (
            <p
              className="rise-in mt-1.5 text-sm text-pretty text-sea-ink-soft"
              style={{ animationDelay: "120ms" }}
            >
              {formSubheading}
            </p>
          ) : null}

          <div className="rise-in mt-6" style={{ animationDelay: "180ms" }}>
            {children}
          </div>

          {footerLink ? (
            <p
              className="rise-in mt-6 text-sm text-sea-ink-soft"
              style={{ animationDelay: "240ms" }}
            >
              {footerLink.text}{" "}
              <Link
                to={footerLink.to}
                className={`font-semibold underline-offset-4 transition-colors hover:text-primary focus-visible:underline ${FOCUS_RING}`}
              >
                {footerLink.linkLabel}
              </Link>
            </p>
          ) : null}

          <Link
            to="/"
            search={{ lang: undefined }}
            className={`rise-in mt-8 inline-flex items-center gap-1.5 rounded-md text-sm text-muted-foreground no-underline transition-colors hover:text-primary ${FOCUS_RING}`}
            style={{ animationDelay: "300ms" }}
          >
            <HugeiconsIcon
              icon={ArrowLeft01Icon}
              strokeWidth={2}
              className="size-4"
            />
            {t("general.home")}
          </Link>
        </div>
      </div>
    </div>
  )
}

function BrandPanel({
  kicker,
  title,
  subtitle,
  points,
}: Pick<AuthLayoutProps, "kicker" | "title" | "subtitle" | "points">) {
  return (
    <aside className="hidden flex-col justify-center border-r border-border/40 bg-muted/30 p-14 lg:flex lg:p-16">
      <div className="rise-in max-w-md" style={{ animationDelay: "0ms" }}>
        <p className="island-kicker mb-3">{kicker}</p>
        <h1 className="display-title mb-4 text-4xl leading-[1.05] font-bold tracking-tight text-balance text-sea-ink">
          {title}
        </h1>
        {subtitle ? (
          <p className="mb-8 text-base text-pretty text-sea-ink-soft">
            {subtitle}
          </p>
        ) : null}

        <ul className="space-y-3">
          {points.map((point, index) => (
            <li
              key={point.label}
              className="rise-in flex items-start gap-3 text-sm text-sea-ink"
              style={{ animationDelay: `${index * 90 + 120}ms` }}
            >
              <HugeiconsIcon
                icon={point.icon}
                strokeWidth={2}
                className="mt-0.5 size-5 shrink-0 text-primary"
              />
              {point.label}
            </li>
          ))}
        </ul>
      </div>
    </aside>
  )
}
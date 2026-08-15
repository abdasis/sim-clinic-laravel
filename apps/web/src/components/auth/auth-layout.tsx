import { Link } from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import { ArrowLeft01Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"

/** Tombol kirim halaman masuk: selebar form, terangkat sedikit saat disentuh. */
export const AUTH_SUBMIT_CLASS =
  "w-full transition-transform duration-150 ease-out hover:-translate-y-px"

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

/**
 * Kerangka halaman masuk: panel brand di kiri, form di kanan, selebar layar.
 * Dipakai empat route yang tidak sekerabat, jadi tinggal di components/
 * global — bukan dikolokasi ke salah satu subtree.
 *
 * ponytail: form sengaja tidak dibungkus Card — mengambang di atas aurora,
 * mengikuti arah visual bersih tanpa bingkai ganda. Bungkus dengan Card bila
 * kelak form-nya cukup padat sehingga butuh batas yang tegas.
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
        <p className="island-kicker mb-6 lg:hidden">{kicker}</p>

        <div
          className="rise-in w-full max-w-sm"
          style={{ animationDelay: "60ms" }}
        >
          <h2 className="display-title text-2xl font-bold tracking-tight text-pretty text-sea-ink">
            {formHeading}
          </h2>
          {formSubheading ? (
            <p className="mt-1.5 text-sm text-sea-ink-soft">
              {formSubheading}
            </p>
          ) : null}

          <div className="mt-6">{children}</div>

          {footerLink ? (
            <p className="mt-6 text-sm text-sea-ink-soft">
              {footerLink.text}{" "}
              <Link to={footerLink.to} className="font-semibold">
                {footerLink.linkLabel}
              </Link>
            </p>
          ) : null}

          <Link
            to="/"
            search={{ lang: undefined }}
            className="mt-8 inline-flex items-center gap-1.5 text-sm text-sea-ink-soft no-underline transition-colors hover:text-sea-ink"
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
    <div className="hidden items-center lg:flex lg:p-14">
      <section className="island-shell rise-in relative overflow-hidden rounded-[2rem] p-10">
        <div className="auth-glow-a pointer-events-none absolute -top-24 -left-20 h-56 w-56 rounded-full" />
        <div className="auth-glow-b pointer-events-none absolute -right-20 -bottom-20 h-56 w-56 rounded-full" />

        <p className="island-kicker mb-3">{kicker}</p>
        <h1 className="display-title mb-4 text-4xl leading-[1.05] font-bold tracking-tight text-balance text-sea-ink">
          {title}
        </h1>
        {subtitle ? (
          <p className="mb-8 max-w-md text-base text-sea-ink-soft">
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
                className="mt-0.5 size-5 shrink-0 text-lagoon-deep"
              />
              {point.label}
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}

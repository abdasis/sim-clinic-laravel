import { Link } from "@tanstack/react-router"

import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { Mascot } from "./mascot.tsx"

interface DashboardHeroProps {
  tenant: string
  userName?: string
  clinicRole: string
}

/**
 * Kepala dasbor: sapaan dan satu aksi utama sesuai peran. Sengaja satu
 * tombol — kalau semua modul ditawarkan sekaligus, tidak ada yang menonjol.
 */
export function DashboardHero({
  tenant,
  userName,
  clinicRole,
}: DashboardHeroProps) {
  const { t } = useTrans()

  const greeting = userName
    ? t("dashboard.welcome").replace(":name", userName)
    : t("dashboard.welcome_generic")

  return (
    <section className="rise-in relative overflow-hidden rounded-xl border border-border/50 bg-gradient-to-br from-[color-mix(in_oklab,var(--lagoon)_14%,transparent)] to-transparent p-5 shadow-sm">
      <div className="flex items-center gap-4">
        <Mascot className="hidden size-20 shrink-0 sm:block" />

        <div className="min-w-0 flex-1">
          <h1 className="text-lg font-semibold tracking-tight text-balance">
            {greeting}
          </h1>
          <p className="mt-0.5 text-sm text-muted-foreground text-pretty">
            {t("dashboard.subtitle")}
          </p>
        </div>

        <RoleAction tenant={tenant} clinicRole={clinicRole} />
      </div>
    </section>
  )
}

function RoleAction({
  tenant,
  clinicRole,
}: {
  tenant: string
  clinicRole: string
}) {
  const { t } = useTrans()

  // Kasir hampir selalu menuju kasir; admin ke laporan; sisanya ke jadwal.
  if (clinicRole === "cashier") {
    return (
      <HeroButton
        label={t("dashboard.cta.cashier")}
        hint={t("dashboard.cta.hint_cashier")}
        shortcut="g p"
      >
        <Link to="/$tenant/clinic/pos" params={{ tenant }}>
          {t("dashboard.cta.cashier")}
        </Link>
      </HeroButton>
    )
  }

  if (clinicRole === "admin") {
    return (
      <HeroButton
        label={t("dashboard.cta.admin")}
        hint={t("dashboard.cta.hint_admin")}
        shortcut="g r"
      >
        <Link to="/$tenant/clinic/reports" params={{ tenant }}>
          {t("dashboard.cta.admin")}
        </Link>
      </HeroButton>
    )
  }

  return (
    <HeroButton
      label={t("dashboard.cta.clinical")}
      hint={t("dashboard.cta.hint_clinical")}
      shortcut="g b"
    >
      <Link to="/$tenant/clinic/bookings" params={{ tenant }}>
        {t("dashboard.cta.clinical")}
      </Link>
    </HeroButton>
  )
}

function HeroButton({
  label,
  hint,
  shortcut,
  children,
}: {
  label: string
  hint: string
  shortcut: string
  children: React.ReactNode
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          asChild
          aria-label={label}
          className="hidden shrink-0 transition-transform duration-200 ease-[var(--ease-out)] active:scale-[0.96] sm:inline-flex"
        >
          {children}
        </Button>
      </TooltipTrigger>
      <TooltipContent className="flex items-center gap-2">
        {hint}
        <Kbd>{shortcut}</Kbd>
      </TooltipContent>
    </Tooltip>
  )
}

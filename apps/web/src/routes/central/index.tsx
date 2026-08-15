import { createFileRoute, Link, useNavigate } from "@tanstack/react-router"
import { useEffect } from "react"
import { Building2 } from "lucide-react"
import { Building03Icon } from "@hugeicons/core-free-icons"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useCentralStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { hasPlatformRole } from "#/lib/auth.ts"

export const Route = createFileRoute("/central/")({
  component: CentralDashboardPage,
})

function CentralDashboardPage() {
  const { t } = useTrans()
  const navigate = useNavigate()
  // ponytail: null saat SSR & first client render, update setelah mount — mencegah hydration mismatch dari localStorage.
  const user = useAuthUser()

  const stats = useCentralStats()

  useEffect(() => {
    if (!hasPlatformRole()) {
      navigate({ to: "/central/login", replace: true })
    }
  }, [navigate])

  // Shortcut vim-style: g lalu t untuk membuka daftar tenant.
  useEffect(() => {
    let pending = false
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null
      if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return
      if (e.metaKey || e.ctrlKey || e.altKey) return

      if (e.key === "g") {
        pending = true
        return
      }
      if (pending && e.key === "t") {
        navigate({ to: "/central/tenants" })
      }
      pending = false
    }
    window.addEventListener("keydown", onKey)
    return () => window.removeEventListener("keydown", onKey)
  }, [navigate])

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-6">
      <ClinicBreadcrumb
        items={[
          { label: t("general.central"), to: "/central/tenants" },
          { label: t("central.dashboard") },
        ]}
      />

      <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold tracking-tight">
            {t("central.dashboard")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {user?.name
              ? t("central.welcome").replace(":name", user.name)
              : t("central.welcome_generic")}
          </p>
        </div>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button asChild size="sm" variant="outline">
              <Link to="/central/tenants">
                <Building2 className="size-4" />
                {t("central.manage_tenants")}
              </Link>
            </Button>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("central.manage_tenants")}
            <span className="flex items-center gap-0.5">
              <Kbd>g</Kbd>
              <Kbd>t</Kbd>
            </span>
          </TooltipContent>
        </Tooltip>
      </div>

      <IndexCta
        icon={Building03Icon}
        tone="lagoon"
        mascot="platform"
        title={t("cta.central.title")}
        description={t("cta.central.description")}
        action={
          <Button asChild className="transition-transform duration-150 ease-out hover:-translate-y-px">
            <Link to="/central/tenants">{t("cta.central.action")}</Link>
          </Button>
        }
      />

      <StatsSection
        rangeLabel={t("stats.last_weeks").replace(
          ":weeks",
          String(stats.data?.meta.weeks ?? 12),
        )}
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />
    </div>
  )
}

import { createFileRoute, Link, useNavigate } from "@tanstack/react-router"
import { useEffect } from "react"
import { useQuery } from "@tanstack/react-query"
import { Building2, CircleSlash, CircleCheck } from "lucide-react"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { hasPlatformRole } from "#/lib/auth.ts"
import { StatTile } from "./components/stat-tile.tsx"

export const Route = createFileRoute("/central/")({
  component: CentralDashboardPage,
})

interface TenantListResponse {
  meta: { total: number }
}

function useTenantCount(status?: string) {
  return useQuery({
    queryKey: ["central-tenant-count", status ?? "all"],
    queryFn: () =>
      apiGet<TenantListResponse>("/central/tenants", {
        per_page: 1,
        ...(status ? { filter: { status } } : {}),
      }),
    select: (res) => res.meta.total,
  })
}

function CentralDashboardPage() {
  const { t } = useTrans()
  const navigate = useNavigate()
  // ponytail: null saat SSR & first client render, update setelah mount — mencegah hydration mismatch dari localStorage.
  const user = useAuthUser()

  const total = useTenantCount()
  const active = useTenantCount("active")
  const inactive = useTenantCount("inactive")

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

  const failed = total.isError || active.isError || inactive.isError

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

        <TooltipProvider>
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
        </TooltipProvider>
      </div>

      <p className="mb-3 text-sm text-muted-foreground">
        {t("central.summary")}
      </p>

      {failed ? (
        <div className="rounded-lg border border-border/60 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
          {t("central.load_failed")}
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <StatTile
            label={t("central.total_tenants")}
            value={total.data}
            icon={Building2}
            loading={total.isLoading}
          />
          <StatTile
            label={t("central.active_tenants")}
            value={active.data}
            icon={CircleCheck}
            loading={active.isLoading}
          />
          <StatTile
            label={t("central.inactive_tenants")}
            value={inactive.data}
            icon={CircleSlash}
            loading={inactive.isLoading}
          />
        </div>
      )}

      {total.isLoading ? <Skeleton className="mt-3 h-1 w-full" /> : null}
    </div>
  )
}

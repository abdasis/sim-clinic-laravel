import { Link, Outlet, createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"
import { ArrowLeft01Icon, Logout01Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { clearAuth } from "#/lib/auth.ts"

export const Route = createFileRoute("/$tenant/clinic/pos")({
  component: PosLayout,
})

/**
 * Kasir bekerja seharian di satu layar, jadi POS punya kerangkanya sendiri:
 * setinggi viewport, tanpa sidebar, dengan bar atas setipis mungkin. Clinic
 * layout melepaskan `/pos/*` lebih dulu supaya tidak ada chrome ganda.
 *
 * Padding sengaja diserahkan ke tiap halaman — invoice perlu mencetak tanpa
 * jarak tambahan, sedangkan kasir justru butuh area yang mepet ke tepi.
 */
function PosLayout() {
  const { tenant } = useParams({ from: "/$tenant/clinic/pos" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const user = useAuthUser()

  const handleLogout = () => {
    clearAuth()
    navigate({ to: "/$tenant/login", params: { tenant } })
  }

  return (
    <div className="flex h-dvh flex-col overflow-hidden bg-background">
      <header className="flex h-12 shrink-0 items-center gap-3 border-b border-border/50 px-3 print:hidden">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button asChild variant="ghost" size="sm" className="gap-1.5 px-2">
              <Link to="/$tenant/clinic" params={{ tenant }}>
                <HugeiconsIcon
                  icon={ArrowLeft01Icon}
                  strokeWidth={2}
                  className="size-4"
                />
                <span className="hidden sm:inline">{t("pos.back_to_clinic")}</span>
              </Link>
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t("pos.back_to_clinic")}</TooltipContent>
        </Tooltip>

        <Separator orientation="vertical" className="h-4" />

        <h1 className="text-sm font-semibold tracking-tight">{t("pos.title")}</h1>

        <div className="ml-auto flex items-center gap-2">
          {/* Nama kasir dibaca dari localStorage, jadi baru ada setelah mount;
              ruangnya tidak dipesan supaya tidak ada kotak kosong menganga. */}
          {user?.name ? (
            <span className="hidden text-xs text-muted-foreground sm:inline">
              {user.name}
            </span>
          ) : null}
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label={t("general.logout")}
                onClick={handleLogout}
              >
                <HugeiconsIcon
                  icon={Logout01Icon}
                  strokeWidth={2}
                  className="size-4"
                />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("general.logout")}</TooltipContent>
          </Tooltip>
        </div>
      </header>

      <main className="min-h-0 flex-1 overflow-hidden">
        <Outlet />
      </main>
    </div>
  )
}

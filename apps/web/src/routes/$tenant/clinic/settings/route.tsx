import {
  createFileRoute,
  Link,
  Outlet,
  useParams,
  useRouterState,
} from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"

import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useMe } from "#/hooks/use-appearance.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import {
  SETTINGS_ITEMS,
  visibleSettingsItems,
  type SettingsNavItem,
} from "./components/settings-nav.ts"

export const Route = createFileRoute("/$tenant/clinic/settings")({
  component: SettingsLayout,
})

/**
 * Kerangka halaman Pengaturan: sidebar sekunder di kiri, konten di kanan.
 *
 * Sidebar-nya datar seperti daftar pengaturan Linear — tanpa kartu dan tanpa
 * bayangan. Di layar sempit ia berubah jadi baris tab yang bisa digeser,
 * karena kolom 208px di ponsel hanya menyisakan sepetak untuk isinya.
 */
function SettingsLayout() {
  const { tenant } = useParams({ from: "/$tenant/clinic/settings" })
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  const user = useAuthUser()
  const { data: me } = useMe(tenant)

  const role = user?.clinic_role ?? ""
  const permissions = Array.isArray(me?.permissions) ? me.permissions : null

  const items = visibleSettingsItems(SETTINGS_ITEMS, role, permissions)

  return (
    <div className="flex flex-col gap-4 md:flex-row md:gap-6">
      <SettingsNav tenant={tenant} basePath={pathname} items={items} />
      <div className="min-w-0 flex-1">
        <Outlet />
      </div>
    </div>
  )
}

function SettingsNav({
  tenant,
  basePath,
  items,
}: {
  tenant: string
  basePath: string
  items: SettingsNavItem[]
}) {
  const { t } = useTrans()
  const suffix = settingsSuffix(basePath, tenant)

  return (
    <nav
      aria-label={t("settings.title")}
      className="flex gap-1 overflow-x-auto pb-2 md:block md:w-52 md:shrink-0 md:overflow-visible md:pb-0"
    >
      <ul className="flex min-w-full gap-1 md:sticky md:top-0 md:block md:space-y-0.5">
        {items.map((item) => {
          const active = isSettingsItemActive(suffix, item.match)

          return (
            <li key={item.match || "profile"} className="shrink-0 md:block">
              <Link
                to={item.to}
                params={{ tenant }}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex h-8 items-center gap-2 rounded-lg px-2.5 text-[13px] whitespace-nowrap transition-colors duration-150",
                  "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                  active
                    ? "bg-muted font-medium text-foreground"
                    : "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                )}
              >
                <HugeiconsIcon
                  icon={item.icon}
                  strokeWidth={2}
                  className="size-4 shrink-0"
                />
                {t(item.labelKey)}
              </Link>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}

function settingsSuffix(pathname: string, tenant: string): string {
  const base = `/${tenant}/clinic/settings`

  return pathname === base ? "" : pathname.slice(base.length).replace(/^\/+/, "")
}

function isSettingsItemActive(suffix: string, match: string): boolean {
  if (match === "") return suffix === ""

  return suffix === match || suffix.startsWith(`${match}/`)
}

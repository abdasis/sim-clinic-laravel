import { createFileRoute, Outlet, useNavigate, useRouterState } from "@tanstack/react-router"
import { Building2 } from "lucide-react"

import { AppSidebar, type SidebarNavItem, type SidebarUser } from "#/components/app-sidebar.tsx"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "#/components/ui/sidebar.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { clearAuth, getAuthUser } from "#/lib/auth.ts"

export const Route = createFileRoute("/central")({
  component: CentralLayout,
})

function CentralLayout() {
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  const { t } = useTrans()
  const navigate = useNavigate()

  // Halaman login tetap standalone tanpa chrome sidebar.
  if (pathname === "/central/login") {
    return <Outlet />
  }

  const authUser = getAuthUser()
  const user: SidebarUser = {
    name: authUser?.name ?? "Guest",
    email: authUser?.email ?? "-",
  }

  // ponytail: hanya modul Tenants; tambah item saat modul central baru ada
  const navMain: SidebarNavItem[] = [
    {
      title: t("tenant.tenants"),
      url: "/central/tenants",
      icon: Building2,
      isActive: pathname.startsWith("/central/tenants"),
    },
  ]

  const handleLogout = () => {
    clearAuth()
    navigate({ to: "/central/login" })
  }

  return (
    <SidebarProvider>
      <AppSidebar
        brandTitle={t("general.central")}
        brandSubtitle={t("general.admin_panel")}
        brandTo="/central/tenants"
        groupLabel={t("general.platform")}
        navMain={navMain}
        user={user}
        onLogout={handleLogout}
      />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="mr-1 h-4" />
          <h1 className="text-sm font-semibold">{sectionTitle(pathname, t)}</h1>
        </header>
        <main className="flex-1 p-4">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  )
}

function sectionTitle(pathname: string, t: (key: string) => string): string {
  if (pathname.startsWith("/central/tenants")) return t("tenant.tenants")
  return t("general.central")
}

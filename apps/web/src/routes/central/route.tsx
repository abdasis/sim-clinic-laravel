import { createFileRoute, Outlet, useNavigate, useRouterState } from "@tanstack/react-router"
import { Building2, LayoutDashboard } from "lucide-react"

import { AppSidebar, type SidebarNavItem, type SidebarUser } from "#/components/app-sidebar.tsx"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "#/components/ui/sidebar.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { clearAuth } from "#/lib/auth.ts"
import { ShellSkeleton } from "#/components/shell-skeleton.tsx"

export const Route = createFileRoute("/central")({
  component: CentralLayout,
})

function CentralLayout() {
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  const { t, ready } = useTrans()
  const navigate = useNavigate()

  // Halaman login tetap standalone tanpa chrome sidebar.
  if (pathname === "/central/login") {
    return <Outlet />
  }

  // ponytail: render sidebar setelah mount; auth dari localStorage tak bisa dibaca server, jadi SSR & first client render pakai shell identik.
  const mounted = useIsMounted()
  const authUser = useAuthUser()
  const user: SidebarUser = {
    name: authUser?.name ?? "Guest",
    email: authUser?.email ?? "-",
  }

  // ponytail: hanya modul Dasbor + Tenants; tambah item saat modul central baru ada
  const navMain: SidebarNavItem[] = [
    {
      title: t("central.dashboard"),
      url: "/central",
      icon: LayoutDashboard,
      isActive: pathname === "/central",
    },
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

  // ponytail: skeleton saat terjemahan belum siap; tidak baca localStorage jadi aman SSR & first paint.
  if (!ready) return <ShellSkeleton navCount={2} />

  return (
    <SidebarProvider>
      {mounted ? (
        <AppSidebar
          brandTitle={t("general.central")}
          brandSubtitle={t("general.admin_panel")}
          brandTo="/central"
          groupLabel={t("general.platform")}
          navMain={navMain}
          user={user}
          onLogout={handleLogout}
        />
      ) : null}
      <SidebarInset>
        <header className="flex h-12 shrink-0 items-center gap-2 border-b px-4">
          {mounted ? <SidebarTrigger /> : null}
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
  if (pathname === "/central") return t("central.dashboard")
  return t("general.central")
}

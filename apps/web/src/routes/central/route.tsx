import { createFileRoute, Outlet, useNavigate, useRouterState } from "@tanstack/react-router"
import {
  Building02Icon,
  DatabaseIcon,
  WhatsappIcon,
  DashboardSquare01Icon,
} from "@hugeicons/core-free-icons"

import { AppSidebar, type SidebarNavItem, type SidebarUser } from "#/components/app-sidebar.tsx"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "#/components/ui/sidebar.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import {
  BreadcrumbTailProvider,
  useBreadcrumbTailValue,
} from "#/components/breadcrumb-tail.tsx"
import {
  ShellBreadcrumb,
  type ShellCrumb,
} from "#/components/shell-breadcrumb.tsx"
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
  const platformGroup = t("general.platform")
  const navMain: SidebarNavItem[] = [
    {
      title: t("central.dashboard"),
      url: "/central",
      icon: DashboardSquare01Icon,
      isActive: pathname === "/central",
      group: platformGroup,
    },
    {
      title: t("tenant.tenants"),
      url: "/central/tenants",
      icon: Building02Icon,
      isActive: pathname.startsWith("/central/tenants"),
      group: platformGroup,
    },
    {
      title: t("waha.title"),
      url: "/central/waha",
      icon: WhatsappIcon,
      isActive: pathname.startsWith("/central/waha"),
      group: platformGroup,
    },
    {
      title: t("backup.title"),
      url: "/central/backup",
      icon: DatabaseIcon,
      isActive: pathname.startsWith("/central/backup"),
      group: platformGroup,
    },
  ]

  const handleLogout = () => {
    clearAuth()
    navigate({ to: "/central/login" })
  }

  // ponytail: skeleton saat terjemahan belum siap; tidak baca localStorage jadi aman SSR & first paint.
  if (!ready) return <ShellSkeleton navCount={4} />

  return (
    <SidebarProvider>
      {mounted ? (
        <AppSidebar
          brandTitle={t("general.central")}
          brandSubtitle={t("general.admin_panel")}
          brandTo="/central"
          navMain={navMain}
          user={user}
          onLogout={handleLogout}
        />
      ) : null}
      <BreadcrumbTailProvider>
        <SidebarInset>
          <header className="flex h-12 shrink-0 items-center gap-2 border-b px-4">
            {mounted ? <SidebarTrigger /> : null}
            <Separator orientation="vertical" className="mr-1 h-4" />
            <CentralCrumbs pathname={pathname} />
          </header>
          <main className="flex-1 p-4">
            <Outlet />
          </main>
        </SidebarInset>
      </BreadcrumbTailProvider>
    </SidebarProvider>
  )
}

/**
 * Breadcrumb shell central. Modulnya sedikit dan tetap, jadi hierarkinya
 * disebut langsung — memaksakan penurunan dari daftar menu di sini hanya
 * menambah lapisan tanpa menghilangkan satu pun pengulangan.
 */
function CentralCrumbs({ pathname }: { pathname: string }) {
  const { t } = useTrans()
  const tail = useBreadcrumbTailValue()

  const items: ShellCrumb[] = [
    { label: t("general.central"), to: "/central" },
  ]

  if (pathname.startsWith("/central/tenants")) {
    items.push({ label: t("tenant.tenants") })
  } else if (pathname.startsWith("/central/waha")) {
    items.push({ label: t("waha.title") })
  } else if (pathname.startsWith("/central/backup")) {
    items.push({ label: t("backup.title") })
  } else {
    items.push({ label: t("central.dashboard") })
  }

  if (tail) {
    items.push({ label: tail })
  }

  return <ShellBreadcrumb items={items} />
}

import {
  createFileRoute,
  Link,
  Outlet,
  useNavigate,
  useParams,
  useRouterState,
} from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import {
  BarChartIcon,
  BubbleChatIcon,
  DiscountTag01Icon,
  MoneyBag02Icon,
  Calendar01Icon,
  CashierIcon,
  DashboardSquare01Icon,
  File02Icon,
  Globe02Icon,
  HeartPulseIcon,
  Layers01Icon,
  PackageIcon,
  PaintBoardIcon,
  StethoscopeIcon,
  UserGroupIcon,
} from "@hugeicons/core-free-icons"

import { AppSidebar, type SidebarNavItem, type SidebarUser } from "#/components/app-sidebar.tsx"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "#/components/ui/sidebar.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useMe } from "#/hooks/use-appearance.ts"
import { normalizeAppearance } from "#/types/appearance.ts"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { clearAuth } from "#/lib/auth.ts"
import { ShellSkeleton } from "#/components/shell-skeleton.tsx"

export const Route = createFileRoute("/$tenant/clinic")({
  component: ClinicLayout,
})

interface NavChild {
  key: string
  label: string
}

interface NavItem {
  key: string
  label: string
  roles: string[] // peran klinik yang boleh melihat modul
  icon: IconSvgElement
  children?: NavChild[]
}

function ClinicLayout() {
  const { tenant } = useParams({ from: "/$tenant/clinic" })
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  const { t, ready } = useTrans()
  const navigate = useNavigate()
  // ponytail: render sidebar setelah mount; auth dari localStorage tak bisa dibaca server, jadi SSR & first client render pakai shell identik (mencegah hydration mismatch).
  const mounted = useIsMounted()
  const user = useAuthUser()
  const role = user?.clinic_role ?? ""
  // Preferensi ikut antar-perangkat: localStorage disegarkan dari server
  // sekali per pemasangan shell, lalu diterapkan ke DOM oleh hook-nya.
  useMe(tenant, mounted)
  const appearance = normalizeAppearance(user?.appearance)

  const base = `/${tenant}/clinic`

  // POS punya kerangka full-screen sendiri (pos/route.tsx). Early return ini
  // sengaja diletakkan setelah semua hook di atas — memindahkannya ke atas
  // akan mengubah jumlah hook antar-render saat berpindah dari halaman klinik
  // ke kasir, dan React menolaknya.
  if (pathname === `${base}/pos` || pathname.startsWith(`${base}/pos/`)) {
    return <Outlet />
  }

  const items: NavItem[] = [
    { key: "", label: t("dashboard.title"), roles: ["admin", "doctor", "therapist", "cashier"], icon: DashboardSquare01Icon },
    { key: "staff", label: t("staff.title"), roles: ["admin"], icon: UserGroupIcon },
    { key: "services", label: t("service.title"), roles: ["admin", "doctor", "therapist"], icon: StethoscopeIcon },
    { key: "patients", label: t("patient.title"), roles: ["admin", "doctor", "therapist", "cashier"], icon: HeartPulseIcon },
    { key: "bookings", label: t("booking.title"), roles: ["admin", "doctor", "therapist"], icon: Calendar01Icon },
    { key: "medical-records", label: t("medical_record.title"), roles: ["admin", "doctor", "therapist"], icon: File02Icon },
    { key: "products", label: t("product.title"), roles: ["admin"], icon: PackageIcon },
    { key: "inventory", label: t("inventory.title"), roles: ["admin"], icon: Layers01Icon },
    {
      key: "pos",
      label: t("pos.title"),
      roles: ["admin", "cashier"],
      // Tidak ada ikon keranjang di set gratis; POS di klinik = meja kasir.
      icon: CashierIcon,
      children: [
        { key: "pos", label: t("pos.add_transaction") },
        { key: "pos/transactions", label: t("pos.transactions") },
      ],
    },
    { key: "promos", label: t("promo.title"), roles: ["admin", "cashier"], icon: DiscountTag01Icon },
    { key: "expenses", label: t("expense.title"), roles: ["admin"], icon: MoneyBag02Icon },
    { key: "broadcasts", label: t("broadcast.title"), roles: ["admin"], icon: BubbleChatIcon },
    { key: "company-profile", label: t("company_profile.title"), roles: ["admin"], icon: Globe02Icon },
    { key: "reports", label: t("report.title"), roles: ["admin"], icon: BarChartIcon },
  ]

  const visible = items.filter((item) => item.roles.includes(role))

  const navMain: SidebarNavItem[] = visible.map((item) => {
    const activeChild = activeChildKey(pathname, base, item)

    return {
      title: item.label,
      url: item.key ? `${base}/${item.key}` : base,
      icon: item.icon,
      isActive: isActiveItem(pathname, base, item),
      items: item.children?.map((child) => ({
        title: child.label,
        url: `${base}/${child.key}`,
        isActive: child.key === activeChild,
      })),
    }
  })

  const sidebarUser: SidebarUser = {
    name: user?.name ?? "Guest",
    email: user?.email ?? "-",
  }

  const handleLogout = () => {
    clearAuth()
    navigate({ to: "/$tenant/login", params: { tenant } })
  }

  // ponytail: skeleton saat terjemahan belum siap; tidak baca localStorage jadi aman SSR & first paint.
  if (!ready) return <ShellSkeleton navCount={visible.length || 8} />

  return (
    <SidebarProvider>
      {mounted ? (
        <AppSidebar
          variant={appearance.sidebar_variant}
          preferencesTo={
            <Link to="/$tenant/clinic/preferences" params={{ tenant }}>
              <HugeiconsIcon icon={PaintBoardIcon} strokeWidth={2} />
              {t("preferences.title")}
            </Link>
          }
          brandTitle={tenant}
          brandSubtitle={t("clinic.clinic")}
          brandTo={navMain[0]?.url ?? base}
          groupLabel={t("clinic.clinic")}
          navMain={navMain}
          user={sidebarUser}
          onLogout={handleLogout}
        />
      ) : null}
      <SidebarInset>
        <header className="flex h-12 shrink-0 items-center gap-2 border-b px-4">
          {mounted ? <SidebarTrigger /> : null}
          <Separator orientation="vertical" className="mr-1 h-4" />
          <h1 className="text-sm font-semibold">
            {mounted ? (sectionTitle(pathname, base, visible) ?? tenant) : tenant}
          </h1>
        </header>
        <main className="flex-1 p-4">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  )
}

function isActiveItem(pathname: string, base: string, item: NavItem): boolean {
  // Dasbor tinggal di base itu sendiri; kalau dicocokkan dengan prefix, ia
  // akan ikut aktif di setiap modul.
  if (item.key === "") {
    return pathname.replace(/\/$/, "") === base
  }

  if (item.children?.length) {
    return item.children.some((child) => pathname.startsWith(`${base}/${child.key}`))
  }
  return pathname.startsWith(`${base}/${item.key}`)
}

/**
 * Kunci submenu yang sedang aktif. Kunci saudara bisa saling berawalan
 * ("pos" dan "pos/transactions"), jadi yang paling panjang yang menang —
 * kalau tidak, keduanya tampak aktif bersamaan.
 */
function activeChildKey(
  pathname: string,
  base: string,
  item: NavItem,
): string | undefined {
  return item.children
    ?.filter((child) => pathname.startsWith(`${base}/${child.key}`))
    .sort((a, b) => b.key.length - a.key.length)
    .at(0)?.key
}

function sectionTitle(
  pathname: string,
  base: string,
  visible: NavItem[],
): string | undefined {
  const parent = visible.find((item) => isActiveItem(pathname, base, item))

  if (!parent) return undefined

  const childKey = activeChildKey(pathname, base, parent)
  const child = parent.children?.find((c) => c.key === childKey)

  return child ? `${parent.label} / ${child.label}` : parent.label
}
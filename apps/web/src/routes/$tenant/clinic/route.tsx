import {
  createFileRoute,
  Outlet,
  useNavigate,
  useParams,
  useRouterState,
} from "@tanstack/react-router"
import {
  BarChart3,
  Boxes,
  Calendar,
  FileText,
  HeartPulse,
  Package,
  ShoppingCart,
  Stethoscope,
  Users,
  UserCog,
  type LucideIcon,
} from "lucide-react"

import { AppSidebar, type SidebarNavItem, type SidebarUser } from "#/components/app-sidebar.tsx"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "#/components/ui/sidebar.tsx"
import { Separator } from "#/components/ui/separator.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { clearAuth, getAuthUser } from "#/lib/auth.ts"

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
  icon: LucideIcon
  children?: NavChild[]
}

function ClinicLayout() {
  const { tenant } = useParams({ from: "/$tenant/clinic" })
  const pathname = useRouterState({ select: (s) => s.location.pathname })
  const { t } = useTrans()
  const navigate = useNavigate()
  const user = getAuthUser()
  const role = user?.clinic_role ?? ""

  const base = `/${tenant}/clinic`

  const items: NavItem[] = [
    { key: "staff", label: t("staff.title"), roles: ["admin"], icon: Users },
    { key: "users", label: t("tenant.users"), roles: ["admin"], icon: UserCog },
    { key: "services", label: t("service.title"), roles: ["admin", "doctor", "therapist"], icon: Stethoscope },
    { key: "patients", label: t("patient.title"), roles: ["admin", "doctor", "therapist", "cashier"], icon: HeartPulse },
    { key: "bookings", label: t("booking.title"), roles: ["admin", "doctor", "therapist", "cashier"], icon: Calendar },
    { key: "medical-records", label: t("medical_record.title"), roles: ["admin", "doctor", "therapist"], icon: FileText },
    { key: "products", label: t("product.title"), roles: ["admin"], icon: Package },
    { key: "inventory", label: t("inventory.title"), roles: ["admin"], icon: Boxes },
    {
      key: "pos",
      label: t("pos.title"),
      roles: ["admin", "cashier"],
      icon: ShoppingCart,
      children: [
        { key: "pos", label: t("pos.add_transaction") },
        { key: "pos/transactions", label: t("pos.transactions") },
      ],
    },
    { key: "reports", label: t("report.title"), roles: ["admin"], icon: BarChart3 },
  ]

  const visible = items.filter((item) => item.roles.includes(role))

  const navMain: SidebarNavItem[] = visible.map((item) => ({
    title: item.label,
    url: `${base}/${item.key}`,
    icon: item.icon,
    isActive: isActiveItem(pathname, base, item),
    items: item.children?.map((child) => ({
      title: child.label,
      url: `${base}/${child.key}`,
    })),
  }))

  const sidebarUser: SidebarUser = {
    name: user?.name ?? "Guest",
    email: user?.email ?? "-",
  }

  const handleLogout = () => {
    clearAuth()
    navigate({ to: "/$tenant/login", params: { tenant } })
  }

  return (
    <SidebarProvider>
      <AppSidebar
        brandTitle={tenant}
        brandSubtitle={t("clinic.clinic")}
        brandTo={navMain[0]?.url ?? base}
        groupLabel={t("clinic.clinic")}
        navMain={navMain}
        user={sidebarUser}
        onLogout={handleLogout}
      />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="mr-1 h-4" />
          <h1 className="text-sm font-semibold">
            {sectionTitle(pathname, base, visible) ?? tenant}
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
  if (item.children?.length) {
    return item.children.some((child) => pathname.startsWith(`${base}/${child.key}`))
  }
  return pathname.startsWith(`${base}/${item.key}`)
}

function sectionTitle(
  pathname: string,
  base: string,
  visible: NavItem[],
): string | undefined {
  const parent = visible.find((item) =>
    isActiveItem(pathname, base, item),
  )
  if (!parent) return undefined
  const child = parent.children?.find((c) =>
    pathname.startsWith(`${base}/${c.key}`),
  )
  return child ? `${parent.label} / ${child.label}` : parent.label
}
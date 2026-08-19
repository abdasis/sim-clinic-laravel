import {
  createFileRoute,
  Link,
  Outlet,
  redirect,
  useNavigate,
  useParams,
  useRouterState,
} from "@tanstack/react-router"
import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import {
  BarChartIcon,
  AiChat01Icon,
  BubbleChatIcon,
  DiscountTag01Icon,
  MoneyBag02Icon,
  Calendar01Icon,
  CashierIcon,
  ClockIcon,
  DashboardSquare01Icon,
  File02Icon,
  Globe02Icon,
  HeartPulseIcon,
  Layers01Icon,
  PackageIcon,
  RulerIcon,
  PaintBoardIcon,
  SecurityLockIcon,
  Settings02Icon,
  StethoscopeIcon,
  TagsIcon,
  UserGroupIcon,
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
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useMe } from "#/hooks/use-appearance.ts"
import { useClinicIdentity } from "#/hooks/use-clinic-identity.ts"
import { normalizeAppearance } from "#/types/appearance.ts"
import { useIsMounted } from "#/hooks/use-is-mounted.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { clearAuth, isAuthenticated } from "#/lib/auth.ts"
import { ShellSkeleton } from "#/components/shell-skeleton.tsx"

export const Route = createFileRoute("/$tenant/clinic")({
  /**
   * Penjaga di tingkat route, bukan di dalam komponen.
   *
   * Tanpa ini, membuka URL klinik tanpa sesi tetap memasang seluruh kerangka
   * halaman: sidebar, judul, dan kerangka tabel sempat terlihat sebelum
   * satu per satu permintaannya ditolak server. Yang bocor bukan datanya,
   * melainkan bentuk aplikasinya — dan pengalamannya membingungkan.
   *
   * Hanya berjalan di peramban: sesi tersimpan di localStorage, yang tidak
   * ada saat halaman dirakit di server. Melewatkannya di sana bukan celah,
   * karena data tetap dijaga token di setiap permintaan API.
   */
  beforeLoad: ({ params }) => {
    if (typeof window === "undefined") return

    if (!isAuthenticated()) {
      throw redirect({ to: "/$tenant/login", params: { tenant: params.tenant } })
    }
  },
  component: ClinicLayout,
})

interface NavChild {
  key: string
  label: string
}

interface NavItem {
  key: string
  label: string
  /**
   * Izin yang menentukan modul ini boleh dibuka. Tanpa ini, item selalu
   * tampil — dipakai dasbor, yang terbuka untuk siapa pun yang login.
   */
  permission?: string
  /**
   * Peran klinik bawaan. Hanya dipakai sebagai cadangan untuk sesi lama yang
   * belum menyimpan daftar izin; begitu izinnya diketahui, izin yang menang.
   */
  roles: string[]
  icon: IconSvgElement
  /**
   * Kunci grup (tanpa prefix `clinic.menu_group.`). Item tanpa `group`
   * tampil di puncak tanpa label. Urutan item mengikuti urutan kemunculan
   * grup, jadi susun item berurutan per grup.
   */
  group?: string
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
  //
  // Daftar izin diambil dari hasil kueri ini, bukan dari localStorage: yang
  // menentukan menu mana yang boleh terlihat tidak boleh berupa nilai yang
  // bisa disunting dari konsol peramban.
  const { data: me } = useMe(tenant, mounted)
  const permissions = Array.isArray(me?.permissions) ? me.permissions : null
  // Identitas dipakai brand sidebar; dibaca semua peran, bukan cuma admin.
  const { data: identity } = useClinicIdentity(tenant, mounted)
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
    // Puncak — tanpa grup.
    { key: "", label: t("dashboard.title"), roles: ["admin", "doctor", "therapist", "cashier"], icon: DashboardSquare01Icon },
    // Operasional.
    { key: "staff", label: t("staff.title"), permission: "staff.view", roles: ["admin"], icon: UserGroupIcon, group: "operations" },
    { key: "roles", label: t("role.title"), permission: "role.view", roles: ["admin"], icon: SecurityLockIcon, group: "operations" },
    { key: "patients", label: t("patient.title"), permission: "patient.view", roles: ["admin", "doctor", "therapist", "cashier"], icon: HeartPulseIcon, group: "operations" },
    { key: "bookings", label: t("booking.title"), permission: "booking.view", roles: ["admin", "doctor", "therapist"], icon: Calendar01Icon, group: "operations" },
    { key: "medical-records", label: t("medical_record.title"), permission: "medical_record.view", roles: ["admin", "doctor", "therapist"], icon: File02Icon, group: "operations" },
    // Katalog & Stok.
    { key: "services", label: t("service.title"), permission: "service.view", roles: ["admin", "doctor", "therapist"], icon: StethoscopeIcon, group: "catalog" },
    { key: "products", label: t("product.title"), permission: "product.view", roles: ["admin"], icon: PackageIcon, group: "catalog" },
    { key: "categories", label: t("category.title"), permission: "category.view", roles: ["admin"], icon: TagsIcon, group: "catalog" },
    { key: "units", label: t("unit.title"), permission: "unit.view", roles: ["admin"], icon: RulerIcon, group: "catalog" },
    { key: "inventory", label: t("inventory.title"), permission: "inventory.view", roles: ["admin"], icon: Layers01Icon, group: "catalog" },
    // Keuangan.
    {
      key: "pos",
      label: t("pos.title"),
      permission: "transaction.view",
      roles: ["admin", "cashier"],
      // Tidak ada ikon keranjang di set gratis; POS di klinik = meja kasir.
      icon: CashierIcon,
      group: "finance",
      children: [
        { key: "pos", label: t("pos.add_transaction") },
        { key: "pos/transactions", label: t("pos.transactions") },
      ],
    },
    { key: "promos", label: t("promo.title"), permission: "promo.view", roles: ["admin", "cashier"], icon: DiscountTag01Icon, group: "finance" },
    { key: "expenses", label: t("expense.title"), permission: "expense.view", roles: ["admin"], icon: MoneyBag02Icon, group: "finance" },
    // Komunikasi & Pengaturan.
    { key: "broadcasts", label: t("broadcast.title"), permission: "broadcast.view", roles: ["admin"], icon: BubbleChatIcon, group: "system" },
    { key: "chatbot/settings", label: t("chatbot.title"), permission: "content.view", roles: ["admin"], icon: AiChat01Icon, group: "system" },
    { key: "company-profile", label: t("company_profile.title"), permission: "content.view", roles: ["admin"], icon: Globe02Icon, group: "system" },
    // Laporan & Log.
    { key: "reports", label: t("report.title"), permission: "report.view", roles: ["admin"], icon: BarChartIcon, group: "insights" },
    { key: "activity-logs", label: t("activity_log.title"), permission: "activity_log.view", roles: ["admin"], icon: ClockIcon, group: "insights" },
  ]

  // Menu mengikuti izin sungguhan, bukan peran yang di-hardcode. Sebelumnya
  // keduanya bisa berbeda: menunya tampil, lalu servernya menolak saat
  // diklik — dan admin tidak punya cara menebak kenapa.
  //
  // Sesi lama belum menyimpan daftar izin. Selama belum diketahui, peran
  // bawaan yang dipakai supaya menunya tidak mendadak kosong sampai /me
  // selesai memuat.
  const visible = items.filter((item) =>
    permissions === null
      ? item.roles.includes(role)
      : item.permission === undefined || permissions.includes(item.permission),
  )

  const navMain: SidebarNavItem[] = visible.map((item) => {
    const activeChild = activeChildKey(pathname, base, item)

    return {
      title: item.label,
      url: item.key ? `${base}/${item.key}` : base,
      icon: item.icon,
      isActive: isActiveItem(pathname, base, item),
      group: item.group ? t(`clinic.menu_group.${item.group}`) : undefined,
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
          clinicSettingsTo={
            // Hanya yang boleh mengubah identitas yang melihat pintunya.
            permissions?.includes("content.manage") ? (
              <Link to="/$tenant/clinic/settings" params={{ tenant }}>
                <HugeiconsIcon icon={Settings02Icon} strokeWidth={2} />
                {t("clinic.settings")}
              </Link>
            ) : undefined
          }
          // Slug hanya cadangan: klinik yang belum mengisi profil tetap
          // punya sesuatu yang bisa dikenali di kepala sidebar.
          brandTitle={identity?.name ?? tenant}
          brandSubtitle={identity?.tagline ?? t("clinic.clinic")}
          brandLogoUrl={identity?.logo_url}
          brandTo={navMain[0]?.url ?? base}
          navMain={navMain}
          user={sidebarUser}
          onLogout={handleLogout}
        />
      ) : null}
      <BreadcrumbTailProvider>
        <SidebarInset>
          <header className="flex h-12 shrink-0 items-center gap-2 border-b px-4">
            {mounted ? <SidebarTrigger /> : null}
            <Separator orientation="vertical" className="mr-1 h-4" />
            <ShellCrumbs
              tenant={tenant}
              base={base}
              pathname={pathname}
              visible={visible}
              mounted={mounted}
            />
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
 * Breadcrumb shell klinik, disusun dari hierarki menu yang sudah dihitung
 * sidebar — jadi tidak ada lagi daftar crumb yang ditulis ulang di tiap
 * halaman dan bisa berbeda-beda bentuknya.
 *
 * Sebelum sidebar terpasang, hanya nama klinik yang ditampilkan: peran
 * pengguna dibaca dari localStorage dan belum tersedia saat render pertama,
 * sehingga menu yang terlihat pun belum bisa dipastikan.
 */
function ShellCrumbs({
  tenant,
  base,
  pathname,
  visible,
  mounted,
}: {
  tenant: string
  base: string
  pathname: string
  visible: NavItem[]
  mounted: boolean
}) {
  const { t } = useTrans()
  const tail = useBreadcrumbTailValue()

  if (!mounted) {
    return <ShellBreadcrumb items={[{ label: tenant }]} />
  }

  const items: ShellCrumb[] = [
    { label: tenant, to: "/$tenant/clinic", params: { tenant } },
    { label: t("clinic.clinic") },
  ]

  const parent = visible.find((item) => isActiveItem(pathname, base, item))

  if (parent && parent.key !== "") {
    items.push({ label: parent.label })

    const childKey = activeChildKey(pathname, base, parent)
    const child = parent.children?.find((c) => c.key === childKey)

    if (child) {
      items.push({ label: child.label })
    }
  } else if (!parent) {
    // Halaman di luar menu, mis. Preferensi. Tanpa cadangan ini breadcrumb
    // berhenti di nama klinik dan tidak menyebut halaman yang sedang dibuka.
    items.push({ label: outsideNavLabel(pathname, base, t) })
  }

  // Halaman di luar menu sudah dilabeli outsideNavLabel; kalau halamannya
  // juga menyetel ekor yang sama, breadcrumb-nya menyebut dirinya dua kali.
  if (tail && items[items.length - 1]?.label !== tail) {
    items.push({ label: tail })
  }

  return <ShellBreadcrumb items={items} />
}

/** Label halaman yang tidak punya entri menu. */
function outsideNavLabel(
  pathname: string,
  base: string,
  t: (key: string) => string,
): string {
  if (pathname.startsWith(`${base}/preferences`)) return t("preferences.title")
  if (pathname.startsWith(`${base}/settings`)) return t("clinic.settings")

  return t("dashboard.title")
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
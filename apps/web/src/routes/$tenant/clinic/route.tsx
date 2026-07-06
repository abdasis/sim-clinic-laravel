import { createFileRoute, Link, Outlet, useParams } from "@tanstack/react-router"
import { useTrans } from "#/hooks/use-trans.ts"
import { getAuthUser } from "#/lib/auth.ts"

export const Route = createFileRoute("/$tenant/clinic")({
  component: ClinicLayout,
})

interface NavItem {
  key: string
  label: string
  roles: string[] // peran klinik yang boleh melihat modul
}

function ClinicLayout() {
  const { tenant } = useParams({ from: "/$tenant/clinic" })
  const { t } = useTrans()
  const user = getAuthUser()
  const role = user?.clinic_role ?? ""

  const items: NavItem[] = [
    { key: "staff", label: t("staff.title"), roles: ["admin"] },
    { key: "services", label: t("service.title"), roles: ["admin", "doctor", "therapist"] },
    { key: "patients", label: t("patient.title"), roles: ["admin", "doctor", "therapist", "cashier"] },
    { key: "bookings", label: t("booking.title"), roles: ["admin", "doctor", "therapist", "cashier"] },
    { key: "medical-records", label: t("medical_record.title"), roles: ["admin", "doctor", "therapist"] },
    { key: "products", label: t("product.title"), roles: ["admin"] },
    { key: "inventory", label: t("inventory.title"), roles: ["admin"] },
    { key: "pos", label: t("pos.title"), roles: ["admin", "cashier"] },
    { key: "reports", label: t("report.title"), roles: ["admin"] },
  ]

  const visible = items.filter((item) => item.roles.includes(role))

  return (
    <div className="mx-auto flex w-full max-w-7xl gap-6 px-4 py-6">
      <aside className="w-56 shrink-0">
        <div className="mb-4 text-sm font-semibold text-muted-foreground">
          {tenant} / {t("clinic.clinic")}
        </div>
        <nav className="flex flex-col gap-1">
          {visible.map((item) => (
            <Link
              key={item.key}
              to={`/$tenant/clinic/${item.key}` as string}
              params={{ tenant }}
              className="rounded-md px-3 py-2 text-sm hover:bg-muted [&.active]:bg-muted [&.active]:font-medium"
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </aside>
      <main className="min-w-0 flex-1">
        <Outlet />
      </main>
    </div>
  )
}

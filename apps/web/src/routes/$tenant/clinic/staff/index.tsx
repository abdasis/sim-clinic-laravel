import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { UserGroupIcon } from "@hugeicons/core-free-icons"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { AccessDenied } from "#/components/ui/access-denied.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { StatusBadge } from "#/components/ui/status-badge.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { useAuthUser } from "#/hooks/use-auth-user.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { StaffFormModal } from "./components/staff-form-modal.tsx"
import { StaffActionsCell } from "./components/staff-actions-cell.tsx"

/** Staf nonaktif tidak boleh terbaca seperti staf aktif dalam satu tabel. */
const STAFF_STATUS_VARIANTS = {
  active: "default",
  inactive: "secondary",
  suspended: "destructive",
} as const

export const Route = createFileRoute("/$tenant/clinic/staff/")({
  component: StaffPage,
})

export interface StaffRow {
  id: number
  name: string
  email: string
  clinic_role: string
  clinic_role_label: string
  status: string
  status_label: string
  can_delete?: boolean
}

function StaffPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/staff/" })
  const [createOpen, setCreateOpen] = useState(false)
  const { t } = useTrans()
  const authUser = useAuthUser()
  // Halaman ini khusus admin; jangan tembak endpoint yang pasti ditolak.
  const stats = useStats({
    tenant,
    module: "staff",
    enabled: authUser?.clinic_role === "admin",
  })

  const columns = useMemo<ColumnDef<StaffRow>[]>(
    () => [
      { accessorKey: "name", header: t("staff.name") },
      { accessorKey: "email", header: t("staff.email") },
      {
        accessorKey: "clinic_role",
        header: t("staff.clinic_role"),
        cell: ({ row }) => <Badge>{row.original.clinic_role_label}</Badge>,
      },
      {
        accessorKey: "status",
        header: t("staff.status"),
        // Kolomnya dulu mencetak nilai mentah dari API, jadi tabel berbahasa
        // Indonesia menampilkan "active" di antara label yang sudah terjemah.
        cell: ({ row }) => (
          <StatusBadge
            status={row.original.status}
            label={row.original.status_label}
            variantMap={STAFF_STATUS_VARIANTS}
          />
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <StaffActionsCell tenant={tenant} staff={row.original} />
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } = useDataTable<StaffRow>({
    queryKey: ["staff", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<StaffRow>>(`/${tenant}/clinic/staff`, {
        page: params.page,
        per_page: params.per_page,
        sort: params.sort,
        direction: params.direction,
        search: params.search,
        filter: params.filters,
      }),
    columns,
  })

  // ponytail: guard role setelah mount — useAuthUser null saat SSR & first client render, update setelah mount (mencegah hydration mismatch dari localStorage).
  if (authUser?.clinic_role !== "admin") {
    return <AccessDenied illustration="staff" description={t("staff.forbidden_desc")} />
  }

  return (
    <div>
      <IndexCta
        icon={UserGroupIcon}
        tone="ink"
        mascot="clinic"
        title={t("cta.staff.title")}
        description={t("cta.staff.description")}
        actionLabel={t("cta.staff.action")}
        onAction={() => setCreateOpen(true)}
      />

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold tracking-tight">{t("staff.title")}</h1>
        <StaffFormModal
          tenant={tenant}
          open={createOpen}
          onOpenChange={setCreateOpen}
        />
      </div>
      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="staff"
        emptyTitle={t("staff.empty_title")}
        emptyDescription={t("staff.empty_desc")}
      />
    </div>
  )
}

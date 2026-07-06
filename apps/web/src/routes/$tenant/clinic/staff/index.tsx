import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { getAuthUser } from "#/lib/auth.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { StaffFormModal } from "./components/staff-form-modal.tsx"
import { StaffActionsCell } from "./components/staff-actions-cell.tsx"

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
}

function StaffPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/staff/" })
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<StaffRow>[]>(
    () => [
      { accessorKey: "name", header: t("staff.name") },
      { accessorKey: "email", header: t("staff.email") },
      {
        accessorKey: "clinic_role",
        header: t("staff.clinic_role"),
        cell: ({ row }) => <Badge>{row.original.clinic_role_label}</Badge>,
      },
      { accessorKey: "status", header: t("staff.status") },
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

  const { table, isLoading, meta } = useDataTable<StaffRow>({
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

  if (getAuthUser()?.clinic_role !== "admin") {
    return (
      <div className="text-sm text-muted-foreground">{t("clinic.forbidden")}</div>
    )
  }

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/staff", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("staff.title") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("staff.title")}</h1>
        <StaffFormModal tenant={tenant} />
      </div>
      <DataTable
        table={table}
        isLoading={isLoading}
        searchPlaceholder={t("general.search")}
        meta={meta}
      />
    </div>
  )
}

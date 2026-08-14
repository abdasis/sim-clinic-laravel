import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { ServiceActionsCell } from "./components/service-actions-cell.tsx"
import { ServiceFormDialog } from "./components/service-form-dialog.tsx"
import { Button } from "#/components/ui/button.tsx"

export const Route = createFileRoute("/$tenant/clinic/services/")({
  component: ServicesPage,
})

interface ServiceRow {
  id: number
  name: string
  description?: string | null
  price: string
  status: string
  status_label: string
}

function ServicesPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/services/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)

  const columns = useMemo<ColumnDef<ServiceRow>[]>(
    () => [
      { accessorKey: "name", header: t("service.name") },
      { accessorKey: "price", header: t("service.price") },
      {
        accessorKey: "status",
        header: t("service.status"),
        cell: ({ row }) => (
          <Badge
            variant={row.original.status === "archived" ? "secondary" : "default"}
          >
            {row.original.status_label}
          </Badge>
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <ServiceActionsCell tenant={tenant} service={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta } = useDataTable<ServiceRow>({
    queryKey: ["services", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<ServiceRow>>(`/${tenant}/clinic/services`, {
        page: params.page,
        per_page: params.per_page,
        sort: params.sort,
        direction: params.direction,
        search: params.search,
        filter: params.filters,
      }),
    columns,
  })

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("service.title") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("service.title")}</h1>
        <Button onClick={() => setCreateOpen(true)}>{t("service.add")}</Button>
      </div>
      <ServiceFormDialog
        tenant={tenant}
        open={createOpen}
        onOpenChange={setCreateOpen}
      />
      <DataTable
        table={table}
        isLoading={isLoading}
        searchPlaceholder={t("general.search")}
        meta={meta}
      />
    </div>
  )
}

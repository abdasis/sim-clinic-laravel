import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Button } from "#/components/ui/button.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"

export const Route = createFileRoute("/$tenant/clinic/patients/")({
  component: PatientsPage,
})

interface PatientRow {
  id: number
  name: string
  phone: string
  gender: string
  gender_label: string
}

function PatientsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/patients/" })
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<PatientRow>[]>(
    () => [
      { accessorKey: "name", header: t("patient.name") },
      { accessorKey: "phone", header: t("patient.phone") },
      {
        accessorKey: "gender",
        header: t("patient.gender"),
        cell: ({ row }) => row.original.gender_label,
      },
    ],
    [t],
  )

  const { table, isLoading, meta } = useDataTable<PatientRow>({
    queryKey: ["patients", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<PatientRow>>(`/${tenant}/clinic/patients`, {
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
          { label: tenant, to: "/$tenant/clinic/patients", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("patient.title") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("patient.title")}</h1>
        <Button asChild>
          <Link to="/$tenant/clinic/patients/new" params={{ tenant }}>
            {t("patient.add")}
          </Link>
        </Button>
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

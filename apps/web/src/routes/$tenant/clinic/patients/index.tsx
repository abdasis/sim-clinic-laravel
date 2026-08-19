import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMemo } from "react"
import { UserAdd01Icon } from "@hugeicons/core-free-icons"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Button } from "#/components/ui/button.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { PatientActionsCell } from "./components/patient-actions-cell.tsx"

export const Route = createFileRoute("/$tenant/clinic/patients/")({
  component: PatientsPage,
})

interface PatientRow {
  id: number
  name: string
  whatsapp: string
  gender: string
  gender_label: string
  referrer_name?: string | null
  can_delete?: boolean
}

function PatientsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/patients/" })
  const { t } = useTrans()
  const stats = useStats({ tenant, module: "patients" })

  const columns = useMemo<ColumnDef<PatientRow>[]>(
    () => [
      { accessorKey: "name", header: t("patient.name") },
      { accessorKey: "whatsapp", header: t("patient.whatsapp") },
      {
        accessorKey: "gender",
        header: t("patient.gender"),
        cell: ({ row }) => row.original.gender_label ?? "-",
      },
      {
        // Kolom ini yang menentukan bonus pasien baru jatuh ke siapa.
        // Sebelumnya tidak tampil di mana pun, jadi pasien yang tersimpan
        // tanpa pembawa tidak bisa dibedakan dari yang ada pembawanya —
        // bonusnya diam-diam tidak pernah muncul.
        accessorKey: "referrer_name",
        header: t("patient.referred_by"),
        cell: ({ row }) =>
          row.original.referrer_name ? (
            <span>{row.original.referrer_name}</span>
          ) : (
            <span className="text-muted-foreground">—</span>
          ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <PatientActionsCell tenant={tenant} patient={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } = useDataTable<PatientRow>({
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
      <IndexCta
        icon={UserAdd01Icon}
        tone="lagoon"
        mascot="clinic"
        title={t("cta.patients.title")}
        description={t("cta.patients.description")}
        action={
          <Button asChild className="transition-transform duration-150 ease-out hover:-translate-y-px">
            <Link to="/$tenant/clinic/patients/new" params={{ tenant }}>
              {t("cta.patients.action")}
            </Link>
          </Button>
        }
      />

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
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
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="patients"
        emptyTitle={t("patient.empty_title")}
        emptyDescription={t("patient.empty_desc")}
      />
    </div>
  )
}

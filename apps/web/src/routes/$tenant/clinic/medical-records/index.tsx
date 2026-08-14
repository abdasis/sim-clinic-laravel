import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"

export const Route = createFileRoute("/$tenant/clinic/medical-records/")({
  component: MedicalRecordsPage,
})

interface MedicalRecordRow {
  id: number
  patient_id: number
  patient_name?: string | null
  author_name?: string | null
  assessment?: string | null
  plan?: string | null
  created_at?: string | null
}

/** Ringkasan satu baris: assessment dulu, jatuh ke plan bila belum diisi. */
function summarise(record: MedicalRecordRow): string | null {
  const text = record.assessment?.trim() || record.plan?.trim()

  return text ? text : null
}

function MedicalRecordsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/medical-records/" })
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<MedicalRecordRow>[]>(
    () => [
      {
        accessorKey: "created_at",
        header: t("medical_record.date"),
        cell: ({ row }) => (
          <span className="tabular-nums whitespace-nowrap text-muted-foreground">
            {formatDateTime(row.original.created_at)}
          </span>
        ),
      },
      {
        accessorKey: "patient_name",
        header: t("medical_record.patient"),
        cell: ({ row }) => (
          <span className="font-medium">{row.original.patient_name ?? "-"}</span>
        ),
      },
      {
        accessorKey: "author_name",
        header: t("medical_record.author"),
        cell: ({ row }) => row.original.author_name ?? "-",
      },
      {
        id: "summary",
        header: t("medical_record.summary"),
        cell: ({ row }) => {
          const summary = summarise(row.original)

          return summary ? (
            <span className="line-clamp-1 text-muted-foreground">{summary}</span>
          ) : (
            <span className="text-muted-foreground/60">
              {t("medical_record.soap_empty")}
            </span>
          )
        },
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button asChild variant="ghost" size="sm">
                    <Link
                      to="/$tenant/clinic/medical-records/$recordId"
                      params={{ tenant, recordId: String(row.original.id) }}
                    >
                      {t("medical_record.detail")}
                    </Link>
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t("medical_record.detail")}</TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta } = useDataTable<MedicalRecordRow>({
    queryKey: ["medical-records", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<MedicalRecordRow>>(
        `/${tenant}/clinic/medical-records`,
        {
          page: params.page,
          per_page: params.per_page,
          sort: params.sort,
          direction: params.direction,
          search: params.search,
          filter: params.filters,
        },
      ),
    columns,
  })

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          { label: t("medical_record.title") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">
            {t("medical_record.title")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {t("medical_record.history")}
          </p>
        </div>
        <Button asChild>
          <Link
            to="/$tenant/clinic/medical-records/new"
            params={{ tenant }}
            search={{ booking: undefined }}
          >
            {t("medical_record.add")}
          </Link>
        </Button>
      </div>
      <DataTable
        table={table}
        isLoading={isLoading}
        searchPlaceholder={t("medical_record.patient")}
        meta={meta}
      />
    </div>
  )
}

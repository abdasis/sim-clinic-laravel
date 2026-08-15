import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { PromoActionsCell } from "./components/promo-actions-cell.tsx"
import {
  PromoFormDialog,
  type PromoFormValues,
} from "./components/promo-form-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/promos/")({
  component: PromosPage,
})

interface PromoRow extends PromoFormValues {
  discount_type_label?: string | null
  status_label: string
  is_running: boolean
  targets_count?: number
}

function PromosPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/promos/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)

  const columns = useMemo<ColumnDef<PromoRow>[]>(
    () => [
      {
        accessorKey: "name",
        header: t("promo.name"),
        cell: ({ row }) => (
          <div className="min-w-0">
            <p className="truncate font-medium">{row.original.name}</p>
            {row.original.description ? (
              <p className="truncate text-xs text-muted-foreground">
                {row.original.description}
              </p>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "discount_value",
        header: t("promo.discount_value"),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {row.original.discount_type === "percent"
              ? `${Number(row.original.discount_value)}%`
              : formatCurrency(Number(row.original.discount_value))}
          </span>
        ),
      },
      {
        id: "period",
        header: t("promo.period"),
        cell: ({ row }) => (
          <span className="text-xs tabular-nums">
            {formatDate(row.original.starts_at)} &ndash;{" "}
            {formatDate(row.original.ends_at)}
          </span>
        ),
      },
      {
        id: "targets",
        header: t("promo.targets"),
        cell: ({ row }) => (
          <span className="text-xs text-muted-foreground tabular-nums">
            {t("promo.targets_count").replace(
              ":count",
              String(row.original.targets_count ?? row.original.targets?.length ?? 0),
            )}
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: t("promo.status"),
        // Status tersimpan dan status sebenarnya bisa berbeda: promo aktif yang
        // tanggalnya belum tiba belum memotong apa pun, dan itu harus terlihat.
        cell: ({ row }) => {
          const running = row.original.is_running
          const inactive = row.original.status !== "active"
          const label = inactive
            ? row.original.status_label
            : running
              ? t("promo.running")
              : new Date(row.original.starts_at) > new Date()
                ? t("promo.scheduled")
                : t("promo.ended")

          return (
            <Badge
              variant={running ? "default" : inactive ? "outline" : "secondary"}
              className="font-normal"
            >
              {label}
            </Badge>
          )
        },
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <PromoActionsCell tenant={tenant} promo={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } = useDataTable<PromoRow>({
    queryKey: ["promos", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<PromoRow>>(`/${tenant}/clinic/promos`, {
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
          { label: tenant, to: "/$tenant/clinic/promos", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("promo.title") },
        ]}
      />

      <div className="mt-4 mb-4 flex items-center justify-between gap-2">
        <h1 className="text-xl font-semibold">{t("promo.title")}</h1>
        <Button onClick={() => setCreateOpen(true)}>{t("promo.add")}</Button>
      </div>

      <PromoFormDialog
        tenant={tenant}
        open={createOpen}
        onOpenChange={setCreateOpen}
      />

      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="default"
        emptyTitle={t("promo.empty_title")}
        emptyDescription={t("promo.empty_desc")}
      />
    </div>
  )
}

import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"

import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  ARCHIVABLE_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { UnitRow } from "#/hooks/use-unit-options.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { UnitActionsCell } from "./components/unit-actions-cell.tsx"
import { UnitFormDialog } from "./components/unit-form-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/units/")({
  component: UnitsPage,
})

function UnitsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/units/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)

  const columns = useMemo<ColumnDef<UnitRow>[]>(
    () => [
      { accessorKey: "name", header: t("unit.name") },
      {
        accessorKey: "usage_count",
        header: t("unit.usage_count"),
        cell: ({ row }) => {
          const count = row.original.usage_count ?? 0

          return count > 0 ? (
            <Badge variant="secondary" className="font-normal tabular-nums">
              {count}
            </Badge>
          ) : (
            <span className="text-xs text-muted-foreground">
              {t("unit.unused")}
            </span>
          )
        },
      },
      {
        accessorKey: "status",
        header: t("unit.status"),
        cell: ({ row }) => (
          <StatusBadge
            status={row.original.status}
            label={row.original.status_label}
            variantMap={ARCHIVABLE_STATUS_VARIANTS}
          />
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <UnitActionsCell
              tenant={tenant}
              unit={row.original}
              usageCount={row.original.usage_count ?? 0}
            />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } =
    useDataTable<UnitRow>({
      queryKey: ["units", tenant],
      queryFn: (params: DataTableParams) =>
        apiGet<DataTableResponse<UnitRow>>(`/${tenant}/clinic/units`, {
          page: params.page,
          per_page: params.per_page,
          sort: params.sort,
          direction: params.direction,
          search: params.search,
          // Halaman kelola menampilkan arsip juga; formulir produk yang
          // menyaring ke satuan aktif.
          filter: { status: "all", ...params.filters },
        }),
      columns,
    })

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold tracking-tight">
            {t("unit.title")}
          </h1>
          <p className="mt-1 text-sm text-pretty text-muted-foreground">
            {t("unit.empty_desc")}
          </p>
        </div>
        <Button onClick={() => setCreateOpen(true)}>{t("unit.add")}</Button>
      </div>

      <UnitFormDialog
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
        emptyTitle={t("unit.empty_title")}
        emptyDescription={t("unit.empty_desc")}
        faceted={[
          {
            columnId: "status",
            title: t("unit.status"),
            options: [
              { value: "active", label: t("unit.status_active") },
              { value: "archived", label: t("unit.status_archived") },
            ],
          },
        ]}
      />
    </div>
  )
}

import { createFileRoute, useParams } from "@tanstack/react-router"
import { TagsIcon } from "@hugeicons/core-free-icons"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import {
  ARCHIVABLE_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { ServiceActionsCell } from "./components/service-actions-cell.tsx"
import { ServiceFormDialog } from "./components/service-form-dialog.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { CatalogImportDialog } from "#/components/import/catalog-import-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/services/")({
  component: ServicesPage,
})

interface ServiceRow {
  id: number
  name: string
  /** Dibawa untuk prefill dialog ubah; tabelnya menampilkan category_label. */
  category_id?: number | null
  category?: string | null
  category_label?: string | null
  description?: string | null
  price: string
  duration_minutes: number
  status: string
  status_label: string
}

function ServicesPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/services/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)
  const [importOpen, setImportOpen] = useState(false)
  const stats = useStats({ tenant, module: "services" })

  const columns = useMemo<ColumnDef<ServiceRow>[]>(
    () => [
      { accessorKey: "name", header: t("service.name") },
      {
        accessorKey: "category",
        header: t("service.category"),
        cell: ({ row }) =>
          row.original.category_label ? (
            <Badge variant="secondary" className="font-normal">
              {row.original.category_label}
            </Badge>
          ) : (
            <span className="text-muted-foreground">-</span>
          ),
      },
      {
        accessorKey: "price",
        header: t("service.price"),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {formatCurrency(Number(row.original.price))}
          </span>
        ),
      },
      {
        accessorKey: "duration_minutes",
        header: t("service.duration_minutes"),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {row.original.duration_minutes}
            <span className="text-muted-foreground ml-1">mnt</span>
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: t("service.status"),
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
            <ServiceActionsCell tenant={tenant} service={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } = useDataTable<ServiceRow>({
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
      <IndexCta
        icon={TagsIcon}
        tone="palm"
        mascot="clinic"
        title={t("cta.services.title")}
        description={t("cta.services.description")}
        actionLabel={t("cta.services.action")}
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
        <h1 className="text-xl font-semibold">{t("service.title")}</h1>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => setImportOpen(true)}>
            {t("service.import")}
          </Button>
          <Button onClick={() => setCreateOpen(true)}>{t("service.add")}</Button>
        </div>
      </div>
      <ServiceFormDialog
        tenant={tenant}
        open={createOpen}
        onOpenChange={setCreateOpen}
      />
      <CatalogImportDialog
        tenant={tenant}
        resource="services"
        templateName="template-layanan.xlsx"
        queryKey="services"
        open={importOpen}
        onOpenChange={setImportOpen}
      />
      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        faceted={[
          {
            columnId: "status",
            title: t("service.status"),
            options: [
              { label: t("service.status_active"), value: "active" },
              { label: t("service.status_archived"), value: "archived" },
            ],
          },
        ]}
        emptyIllustration="default"
        emptyTitle={t("service.empty_title")}
        emptyDescription={t("service.empty_desc")}
      />
    </div>
  )
}

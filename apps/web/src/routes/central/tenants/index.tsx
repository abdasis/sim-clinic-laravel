import { createFileRoute } from "@tanstack/react-router"
import { useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useCentralStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { TenantStatusToggle } from "./components/status-toggle.tsx"

export const Route = createFileRoute("/central/tenants/")({
  component: TenantsPage,
})

interface TenantRow {
  id: number
  name: string
  slug: string
  phone: string
  status: string
}

function TenantsPage() {
  const { t } = useTrans()
  // Ringkasan yang sama dengan dasbor central; React Query berbagi cache-nya.
  const stats = useCentralStats()

  const columns = useMemo<ColumnDef<TenantRow>[]>(
    () => [
      { accessorKey: "name", header: t("tenant.company_name") },
      { accessorKey: "slug", header: t("tenant.slug") },
      { accessorKey: "phone", header: t("tenant.phone") },
      {
        accessorKey: "status",
        header: t("tenant.tenant_status"),
        cell: ({ row }) => (
          <TenantStatusToggle
            id={row.original.id}
            status={row.original.status}
          />
        ),
      },
    ],
    [t],
  )

  const { table, isLoading, meta, isError, refetch } = useDataTable<TenantRow>({
    queryKey: ["tenants"],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<TenantRow>>("/central/tenants", {
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
    <div className="mx-auto w-full max-w-7xl px-4 py-6">
      <ClinicBreadcrumb
        items={[
          { label: t("general.central"), to: "/central/tenants" },
          { label: t("tenant.tenants") },
        ]}
      />
      <StatsSection
        className="mt-2"
        rangeLabel={t("stats.last_weeks").replace(
          ":weeks",
          String(stats.data?.meta.weeks ?? 12),
        )}
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("tenant.tenants")}</h1>
      </div>
      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="tenants"
        emptyTitle={t("central.empty_title")}
        emptyDescription={t("central.empty_desc")}
      />
    </div>
  )
}

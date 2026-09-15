import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"

import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency } from "#/lib/format.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { MembershipTierActionsCell } from "./components/membership-tier-actions-cell.tsx"
import {
  MembershipTierFormDialog,
  type MembershipTierFormValues,
} from "./components/membership-tier-form-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/memberships/")({
  component: MembershipTiersPage,
})

interface MembershipTierRow extends MembershipTierFormValues {
  discount_type_label?: string | null
  status_label: string
  patients_count?: number
}

function MembershipTiersPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/memberships/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)

  const columns = useMemo<ColumnDef<MembershipTierRow>[]>(
    () => [
      {
        accessorKey: "name",
        header: t("membership.tier_name"),
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
        header: t("membership.discount"),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {row.original.discount_type === "percent"
              ? `${Number(row.original.discount_value)}%`
              : formatCurrency(Number(row.original.discount_value))}
          </span>
        ),
      },
      {
        id: "stacks",
        header: t("membership.stacks_with_promo"),
        cell: ({ row }) => (
          <Badge
            variant={row.original.stacks_with_promo ? "default" : "outline"}
            className="font-normal"
          >
            {row.original.stacks_with_promo
              ? t("general.yes")
              : t("general.no")}
          </Badge>
        ),
      },
      {
        id: "patients_count",
        header: t("patient.title"),
        cell: ({ row }) => (
          <span className="text-xs text-muted-foreground tabular-nums">
            {t("membership.member_count").replace(
              ":count",
              String(row.original.patients_count ?? 0),
            )}
          </span>
        ),
      },
      {
        accessorKey: "status",
        header: t("promo.status"),
        cell: ({ row }) => (
          <Badge
            variant={row.original.status === "active" ? "default" : "outline"}
            className="font-normal"
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
            <MembershipTierActionsCell tenant={tenant} tier={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } =
    useDataTable<MembershipTierRow>({
      queryKey: ["membership-tiers", tenant],
      queryFn: (params: DataTableParams) =>
        apiGet<DataTableResponse<MembershipTierRow>>(
          `/${tenant}/clinic/membership-tiers`,
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
      <div className="mt-4 mb-4 flex items-center justify-between gap-2">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("membership.title")}
        </h1>
        <Button onClick={() => setCreateOpen(true)}>
          {t("membership.add")}
        </Button>
      </div>

      <MembershipTierFormDialog
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
        emptyTitle={t("membership.empty_title")}
        emptyDescription={t("membership.empty_desc")}
      />
    </div>
  )
}

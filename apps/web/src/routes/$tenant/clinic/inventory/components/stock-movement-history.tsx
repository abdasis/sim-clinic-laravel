import { useMemo } from "react"
import { Link } from "@tanstack/react-router"
import type { ColumnDef } from "@tanstack/react-table"

import { DataTable } from "#/components/datatable/datatable.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"

interface Movement {
  id: number
  created_at: string | null
  type: string
  type_label: string
  quantity: number
  balance_after: number
  related_type: string | null
  related_id: number | null
  note: string | null
}

interface Props {
  tenant: string
  productId: string
}

export function StockMovementHistory({ tenant, productId }: Props) {
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<Movement>[]>(
    () => [
      {
        accessorKey: "created_at",
        header: t("inventory.created_at"),
        cell: ({ row }) => (
          <span className="tabular-nums whitespace-nowrap text-muted-foreground">
            {formatDateTime(row.original.created_at)}
          </span>
        ),
      },
      {
        accessorKey: "type",
        header: t("inventory.type"),
        cell: ({ row }) => row.original.type_label,
      },
      {
        accessorKey: "quantity",
        header: () => (
          <div className="text-right">{t("inventory.quantity")}</div>
        ),
        cell: ({ row }) => (
          <div className="text-right font-medium tabular-nums">
            {/* Tanda arah lebih cepat dibaca daripada nama jenis mutasi. */}
            {row.original.type === "in" || row.original.type === "rollback"
              ? "+"
              : "−"}
            {row.original.quantity}
          </div>
        ),
      },
      {
        accessorKey: "balance_after",
        header: () => (
          <div className="text-right">{t("inventory.balance_after")}</div>
        ),
        cell: ({ row }) => (
          <div className="text-right tabular-nums">
            {row.original.balance_after}
          </div>
        ),
      },
      {
        accessorKey: "note",
        header: t("inventory.note"),
        cell: ({ row }) => (
          <span className="text-muted-foreground">
            {row.original.note ?? "-"}
          </span>
        ),
      },
      {
        id: "related",
        header: t("inventory.related_transaction"),
        cell: ({ row }) =>
          row.original.related_type === "transaction" &&
          row.original.related_id ? (
            <Link
              to="/$tenant/clinic/pos/transactions/$id"
              params={{ tenant, id: String(row.original.related_id) }}
              className="text-sm underline-offset-4 transition-colors hover:underline"
            >
              #{row.original.related_id}
            </Link>
          ) : (
            <span className="text-muted-foreground">-</span>
          ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta } = useDataTable<Movement>({
    queryKey: ["stock-movements", tenant, productId],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<Movement>>(
        `/${tenant}/clinic/products/${productId}/stock-movements`,
        {
          page: params.page,
          per_page: params.per_page,
          sort: params.sort,
          direction: params.direction,
          search: params.search,
        },
      ),
    columns,
  })

  return (
    <div className="mt-8">
      <h2 className="mb-3 text-lg font-semibold tracking-tight">
        {t("inventory.history")}
      </h2>
      <DataTable
        table={table}
        isLoading={isLoading}
        meta={meta}
        emptyMessage={t("inventory.empty_movements")}
      />
    </div>
  )
}

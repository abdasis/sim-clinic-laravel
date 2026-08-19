import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { ReceiptDollarIcon } from "@hugeicons/core-free-icons"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import {
  PAYMENT_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { TransactionActionsCell } from "./components/transaction-actions-cell.tsx"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { formatCurrency } from "#/lib/format.ts"

// Tiga keadaan yang sama dengan enum backend.
const PAYMENT_STATUS_OPTIONS = ["unpaid", "partially_paid", "paid"] as const

export const Route = createFileRoute("/$tenant/clinic/pos/transactions/")({
  component: TransactionsPage,
})

interface TransactionRow {
  id: number
  invoice_number: string
  patient_name: string | null
  subtotal: string
  paid_amount: string
  outstanding_amount: number
  payment_status: string
  payment_status_label: string
  cancelled_at: string | null
}

function TransactionsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/pos/transactions/" })
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<TransactionRow>[]>(
    () => [
      {
        accessorKey: "invoice_number",
        header: t("invoice.invoice_number"),
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <span className="tabular-nums">{row.original.invoice_number}</span>
            {/* Transaksi yang dibatalkan tetap terlihat di daftar; tanpa
                penanda, barisnya tampak sama dengan yang masih berlaku. */}
            {row.original.cancelled_at ? (
              <Badge variant="outline" className="font-normal text-destructive">
                {t("pos.cancelled_badge")}
              </Badge>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "patient_name",
        header: t("pos.patient"),
        cell: ({ row }) => row.original.patient_name ?? "-",
      },
      {
        accessorKey: "subtotal",
        header: t("pos.subtotal"),
        cell: ({ row }) => formatCurrency(Number(row.original.subtotal)),
      },
      {
        accessorKey: "paid_amount",
        header: t("pos.paid_amount"),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {formatCurrency(Number(row.original.paid_amount))}
          </span>
        ),
      },
      {
        accessorKey: "outstanding_amount",
        header: t("pos.outstanding"),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {formatCurrency(Number(row.original.outstanding_amount))}
          </span>
        ),
      },
      {
        accessorKey: "payment_status",
        header: t("pos.payment_status"),
        cell: ({ row }) => (
          <StatusBadge
            status={row.original.payment_status}
            label={
              row.original.payment_status_label ??
              t(`clinic.payment_status.${row.original.payment_status}`)
            }
            variantMap={PAYMENT_STATUS_VARIANTS}
          />
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <TransactionActionsCell tenant={tenant} transaction={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const stats = useStats({ tenant, module: "transactions" })

  const { table, isLoading, meta, isError, refetch, error } = useDataTable<TransactionRow>({
    queryKey: ["transactions", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<TransactionRow>>(`/${tenant}/clinic/transactions`, {
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
    <div className="h-full overflow-y-auto p-4">
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
          { label: t("pos.transactions") },
        ]}
      />
      <IndexCta
        icon={ReceiptDollarIcon}
        tone="sunset"
        mascot="clinic"
        title={t("cta.transactions.title")}
        description={t("cta.transactions.description")}
        action={
          <Button asChild className="transition-transform duration-150 ease-out hover:-translate-y-px">
            <Link to="/$tenant/clinic/pos" params={{ tenant }}>
              {t("cta.transactions.action")}
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

      <h1 className="mb-4 text-xl font-semibold">{t("pos.transactions")}</h1>
      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="pos"
        emptyTitle={t("pos.empty_title")}
        emptyDescription={t("pos.empty_desc")}
        faceted={[
          {
            columnId: "payment_status",
            title: t("pos.payment_status"),
            options: PAYMENT_STATUS_OPTIONS.map((status) => ({
              label: t(`clinic.payment_status.${status}`),
              value: status,
            })),
          },
        ]}
      />
    </div>
  )
}

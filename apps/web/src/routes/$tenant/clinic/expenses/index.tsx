import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import { useQuery } from "@tanstack/react-query"
import type { ColumnDef } from "@tanstack/react-table"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { CommissionPanel, PeriodFields } from "./components/commission-panel.tsx"
import { ExpenseActionsCell } from "./components/expense-actions-cell.tsx"
import {
  ExpenseFormDialog,
  type ExpenseFormValues,
} from "./components/expense-form-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/expenses/")({
  component: ExpensesPage,
})

interface ExpenseRow extends ExpenseFormValues {
  category_label: string
  recorded_by_name?: string | null
}

interface SummaryRow {
  category: string
  category_label: string
  total: number
  entries: number
}

/** Awal dan akhir bulan berjalan — periode yang paling sering dipakai. */
function monthRange() {
  const now = new Date()
  const first = new Date(now.getFullYear(), now.getMonth(), 1)
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  const iso = (date: Date) =>
    new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .slice(0, 10)

  return { from: iso(first), to: iso(last) }
}

function ExpensesPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/expenses/" })
  const { t } = useTrans()

  const initial = useMemo(monthRange, [])
  const [from, setFrom] = useState(initial.from)
  const [to, setTo] = useState(initial.to)
  const [createOpen, setCreateOpen] = useState(false)
  const [preset, setPreset] = useState<
    { amount: number; description: string } | undefined
  >()

  const summary = useQuery({
    queryKey: ["expenses", tenant, "summary", from, to],
    queryFn: () =>
      apiGet<{
        data: { by_category: SummaryRow[]; total: number; entries: number }
      }>(`/${tenant}/clinic/expenses/summary`, { from, to }),
    enabled: Boolean(from && to),
  })

  const columns = useMemo<ColumnDef<ExpenseRow>[]>(
    () => [
      {
        accessorKey: "spent_at",
        header: t("expense.spent_at"),
        cell: ({ row }) => (
          <span className="text-xs tabular-nums">
            {formatDate(row.original.spent_at)}
          </span>
        ),
      },
      {
        accessorKey: "description",
        header: t("expense.description"),
        cell: ({ row }) => (
          <div className="min-w-0">
            <p className="truncate font-medium">{row.original.description}</p>
            {row.original.note ? (
              <p className="truncate text-xs text-muted-foreground">
                {row.original.note}
              </p>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "category",
        header: t("expense.category_label"),
        cell: ({ row }) => (
          <Badge variant="secondary" className="font-normal">
            {row.original.category_label}
          </Badge>
        ),
      },
      {
        accessorKey: "amount",
        header: t("expense.amount"),
        cell: ({ row }) => (
          <span className="font-medium tabular-nums">
            {formatCurrency(Number(row.original.amount))}
          </span>
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <ExpenseActionsCell tenant={tenant} expense={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta } = useDataTable<ExpenseRow>({
    queryKey: ["expenses", tenant, from, to],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<ExpenseRow>>(`/${tenant}/clinic/expenses`, {
        page: params.page,
        per_page: params.per_page,
        sort: params.sort,
        direction: params.direction,
        search: params.search,
        filter: { ...params.filters, from, to },
      }),
    columns,
  })

  return (
    <div className="space-y-4">
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/expenses", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("expense.title") },
        ]}
      />

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">{t("expense.title")}</h1>
          <p className="text-sm text-muted-foreground">{t("expense.summary")}</p>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <PeriodFields from={from} to={to} onFrom={setFrom} onTo={setTo} />
          <Button
            onClick={() => {
              setPreset(undefined)
              setCreateOpen(true)
            }}
          >
            {t("expense.add")}
          </Button>
        </div>
      </div>

      {/* Rekap periode: bentuknya mengikuti blok "Rincian Pengeluaran" di
          laporan bulanan klinik, dengan total di ujung. */}
      {summary.isLoading ? (
        <Skeleton className="h-24 w-full" />
      ) : (
        <section className="rounded-lg border border-border/50 bg-card p-4">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h2 className="text-sm font-semibold">{t("expense.summary")}</h2>
            <span className="text-xs text-muted-foreground tabular-nums">
              {t("expense.entries").replace(
                ":count",
                String(summary.data?.data.entries ?? 0),
              )}
            </span>
          </div>

          <dl className="mt-3 space-y-1">
            {(summary.data?.data.by_category ?? []).map((row) => (
              <div
                key={row.category}
                className="flex items-baseline justify-between gap-3 text-sm"
              >
                <dt className="truncate text-muted-foreground">
                  {row.category_label}
                </dt>
                <dd className="shrink-0 tabular-nums">
                  {formatCurrency(row.total)}
                </dd>
              </div>
            ))}
          </dl>

          <div className="mt-3 flex items-baseline justify-between gap-3 border-t border-border/50 pt-3">
            <span className="text-sm font-medium">{t("expense.total")}</span>
            <span className="text-lg font-semibold tabular-nums">
              {formatCurrency(summary.data?.data.total ?? 0)}
            </span>
          </div>
        </section>
      )}

      <CommissionPanel
        tenant={tenant}
        from={from}
        to={to}
        onPost={(next) => {
          setPreset(next)
          setCreateOpen(true)
        }}
      />

      <ExpenseFormDialog
        tenant={tenant}
        open={createOpen}
        onOpenChange={setCreateOpen}
        preset={
          preset
            ? {
                amount: preset.amount,
                description: preset.description,
                category: "incentive",
                spent_at: to,
              }
            : undefined
        }
      />

      <DataTable
        table={table}
        isLoading={isLoading}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="default"
        emptyTitle={t("expense.empty_title")}
        emptyDescription={t("expense.empty_desc")}
      />
    </div>
  )
}

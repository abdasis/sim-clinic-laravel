import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "#/components/ui/alert-dialog.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { formatCurrency } from "./components/format.ts"

export const Route = createFileRoute("/$tenant/clinic/pos/transactions")({
  component: TransactionsPage,
})

interface TransactionRow {
  id: number
  invoice_number: string
  patient_name: string | null
  subtotal: string
  payment_status: string
  payment_status_label: string
}

function CancelAction({ tenant, id }: { tenant: string; id: number }) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const mutation = useMutation({
    mutationFn: () => apiPost(`/${tenant}/clinic/transactions/${id}/cancel`),
    onSuccess: () => {
      toast.success(t("pos.cancelled"))
      qc.invalidateQueries({ queryKey: ["transactions"] })
      setOpen(false)
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  return (
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger asChild>
        <Button size="sm" variant="ghost" className="text-destructive">
          {t("general.cancel")}
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t("general.confirm")}</AlertDialogTitle>
          <AlertDialogDescription>{t("pos.transactions")}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t("general.no")}</AlertDialogCancel>
          <AlertDialogAction
            variant="destructive"
            onClick={(e) => {
              e.preventDefault()
              mutation.mutate()
            }}
          >
            {t("general.yes")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

function TransactionsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/pos/transactions" })
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<TransactionRow>[]>(
    () => [
      { accessorKey: "invoice_number", header: t("invoice.invoice_number") },
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
        accessorKey: "payment_status",
        header: t("clinic.payment_status.paid"),
        cell: ({ row }) => (
          <Badge variant={row.original.payment_status === "paid" ? "default" : "secondary"}>
            {row.original.payment_status_label ??
              t(`clinic.payment_status.${row.original.payment_status}`)}
          </Badge>
        ),
      },
      {
        id: "actions",
        header: t("general.actions"),
        cell: ({ row }) => (
          <div className="flex items-center gap-1">
            <Button asChild size="sm" variant="ghost">
              <Link
                to="/$tenant/clinic/pos/invoices/$id"
                params={{ tenant, id: String(row.original.id) }}
              >
                {t("invoice.title")}
              </Link>
            </Button>
            <CancelAction tenant={tenant} id={row.original.id} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta } = useDataTable<TransactionRow>({
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
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
          { label: t("pos.transactions") },
        ]}
      />
      <h1 className="mb-4 text-xl font-semibold">{t("pos.transactions")}</h1>
      <DataTable
        table={table}
        isLoading={isLoading}
        searchPlaceholder={t("general.search")}
        meta={meta}
      />
    </div>
  )
}

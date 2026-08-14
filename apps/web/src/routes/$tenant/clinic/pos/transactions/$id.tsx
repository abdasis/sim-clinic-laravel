import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { Printer } from "lucide-react"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  PAYMENT_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency, formatDateTime } from "#/lib/format.ts"
import { PaymentForm } from "../components/payment-form.tsx"
import {
  PaymentHistory,
  type PaymentRow,
} from "../components/payment-history.tsx"

export const Route = createFileRoute("/$tenant/clinic/pos/transactions/$id")({
  component: TransactionDetailPage,
})

interface ItemRow {
  id: number
  name: string
  unit_price: string
  qty: number
  subtotal: string
}

interface TransactionDetail {
  id: number
  invoice_number: string
  patient_name?: string | null
  cashier_name?: string | null
  subtotal: string
  paid_amount: string
  outstanding_amount: string
  payment_status: string
  payment_status_label?: string | null
  issued_at?: string | null
  cancelled_at?: string | null
  items?: ItemRow[]
  payments?: PaymentRow[]
}

function TransactionDetailPage() {
  const { tenant, id } = useParams({
    from: "/$tenant/clinic/pos/transactions/$id",
  })
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["transaction", tenant, id],
    queryFn: () =>
      apiGet<{ data: TransactionDetail }>(`/${tenant}/clinic/transactions/${id}`),
    select: (res) => res.data,
  })

  const outstanding = Number(data?.outstanding_amount ?? 0)
  const paid = Number(data?.paid_amount ?? 0)
  const subtotal = Number(data?.subtotal ?? 0)
  const cancelled = Boolean(data?.cancelled_at)

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          {
            label: t("pos.transactions"),
            to: "/$tenant/clinic/pos/transactions",
            params: { tenant },
          },
          { label: data?.invoice_number ?? t("pos.detail") },
        ]}
      />

      {isLoading || !data ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : (
        <div className="space-y-4">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-semibold tracking-tight tabular-nums">
                  {data.invoice_number}
                </h1>
                <StatusBadge
                  status={data.payment_status}
                  label={data.payment_status_label ?? data.payment_status}
                  variantMap={PAYMENT_STATUS_VARIANTS}
                />
              </div>
              <p className="text-sm text-muted-foreground">
                {data.patient_name ?? "-"}
                {data.cashier_name ? ` · ${data.cashier_name}` : ""}
                {data.issued_at ? ` · ${formatDateTime(data.issued_at)}` : ""}
              </p>
            </div>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button asChild variant="outline" size="sm">
                  <Link
                    to="/$tenant/clinic/pos/invoices/$id"
                    params={{ tenant, id }}
                  >
                    <Printer className="size-4" />
                    {t("pos.view_invoice")}
                  </Link>
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("pos.view_invoice")}</TooltipContent>
            </Tooltip>
          </div>

          <div className="grid gap-3 sm:grid-cols-3">
            <Summary label={t("pos.total")} value={subtotal} />
            <Summary label={t("pos.paid_amount")} value={paid} />
            <Summary
              label={t("pos.outstanding")}
              value={outstanding}
              highlight={outstanding > 0}
            />
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pos.items")}</CardTitle>
            </CardHeader>
            <CardContent className="px-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pos.item")}</TableHead>
                    <TableHead className="text-right">
                      {t("pos.unit_price")}
                    </TableHead>
                    <TableHead className="text-right">{t("pos.qty")}</TableHead>
                    <TableHead className="text-right">
                      {t("pos.subtotal")}
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data.items ?? []).map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>{item.name}</TableCell>
                      <TableCell className="text-right tabular-nums">
                        {formatCurrency(Number(item.unit_price))}
                      </TableCell>
                      <TableCell className="text-right tabular-nums">
                        {item.qty}
                      </TableCell>
                      <TableCell className="text-right font-medium tabular-nums">
                        {formatCurrency(Number(item.subtotal))}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {t("pos.record_payment")}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <PaymentForm
                tenant={tenant}
                transactionId={id}
                outstanding={outstanding}
                disabled={cancelled}
                overpaid={paid > subtotal}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {t("pos.payment_history")}
              </CardTitle>
            </CardHeader>
            <CardContent className="px-0">
              <PaymentHistory payments={data.payments ?? []} />
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}

function Summary({
  label,
  value,
  highlight,
}: {
  label: string
  value: number
  highlight?: boolean
}) {
  return (
    <div className="rounded-lg border border-border/50 p-4">
      <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      <p
        className={
          highlight
            ? "mt-1 text-lg font-semibold tabular-nums text-destructive"
            : "mt-1 text-lg font-semibold tabular-nums"
        }
      >
        {formatCurrency(value)}
      </p>
    </div>
  )
}

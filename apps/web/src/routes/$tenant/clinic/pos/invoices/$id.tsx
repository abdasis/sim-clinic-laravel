import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency, formatDateTime } from "#/lib/format.ts"

export const Route = createFileRoute("/$tenant/clinic/pos/invoices/$id")({
  component: InvoicePage,
})

interface InvoiceItem {
  id: number
  name: string
  unit_price: string
  qty: number
  subtotal: string
}

interface InvoicePayment {
  id: number
  method: string
  method_label?: string | null
  amount: string
  paid_at?: string | null
}

interface InvoiceData {
  id: number
  invoice_number: string
  subtotal: string
  paid_amount: string
  outstanding_amount: string
  payment_status: string
  created_at?: string
  patient_name?: string | null
  cashier_name?: string | null
  clinic_name?: string | null
  items: InvoiceItem[]
  payments?: InvoicePayment[]
}

function InvoicePage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/pos/invoices/$id" })
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["transactions", tenant, id],
    queryFn: () =>
      apiGet<{ data: InvoiceData }>(`/${tenant}/clinic/transactions/${id}`),
  })

  const invoice = data?.data

  return (
    // p-4 dilepas saat cetak supaya kertasnya tidak dapat jarak dobel.
    <div className="h-full overflow-y-auto p-4 print:h-auto print:overflow-visible print:p-0">
      <div className="print:hidden">
        <ClinicBreadcrumb
          items={[
            { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
            { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
            {
              label: t("pos.transactions"),
              to: "/$tenant/clinic/pos/transactions",
              params: { tenant },
            },
            { label: data?.data.invoice_number ?? t("invoice.title") },
          ]}
        />
      </div>

      {isLoading ? (
        <Skeleton className="h-64 w-full" />
      ) : !invoice ? (
        <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
      ) : (
        <div className="mx-auto max-w-2xl space-y-6 rounded-lg border bg-card p-6">
          <div className="flex items-start justify-between">
            <div>
              <h1 className="text-xl font-semibold">{t("invoice.title")}</h1>
              <p className="text-sm text-muted-foreground">
                {invoice.clinic_name ?? tenant}
              </p>
            </div>
            <Button className="print:hidden" onClick={() => window.print()}>
              {t("invoice.print")}
            </Button>
          </div>

          <div className="grid grid-cols-2 gap-2 text-sm">
            <span className="text-muted-foreground">{t("invoice.invoice_number")}</span>
            <span className="text-right font-medium">{invoice.invoice_number}</span>
            <span className="text-muted-foreground">{t("invoice.patient")}</span>
            <span className="text-right">{invoice.patient_name ?? "-"}</span>
            <span className="text-muted-foreground">{t("invoice.cashier")}</span>
            <span className="text-right">{invoice.cashier_name ?? "-"}</span>
            {invoice.created_at ? (
              <>
                <span className="text-muted-foreground">{t("invoice.date")}</span>
                <span className="text-right">
                  {new Date(invoice.created_at).toLocaleString("id-ID")}
                </span>
              </>
            ) : null}
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t("invoice.item")}</TableHead>
                <TableHead className="text-right">{t("invoice.qty")}</TableHead>
                <TableHead className="text-right">{t("invoice.price")}</TableHead>
                <TableHead className="text-right">{t("invoice.subtotal")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {invoice.items.map((item) => (
                <TableRow key={item.id}>
                  <TableCell>{item.name}</TableCell>
                  <TableCell className="text-right tabular-nums">{item.qty}</TableCell>
                  <TableCell className="text-right tabular-nums">
                    {formatCurrency(Number(item.unit_price))}
                  </TableCell>
                  <TableCell className="text-right tabular-nums">
                    {formatCurrency(Number(item.subtotal))}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <div className="flex items-center justify-between border-t pt-4">
            <span className="font-semibold">{t("invoice.total")}</span>
            <span className="text-lg font-semibold tabular-nums">
              {formatCurrency(Number(invoice.subtotal))}
            </span>
          </div>

          {invoice.payments && invoice.payments.length > 0 ? (
            <div className="space-y-2 border-t pt-4">
              <p className="text-sm font-semibold">{t("invoice.payments")}</p>
              <ul className="space-y-1 text-sm">
                {invoice.payments.map((payment) => (
                  <li
                    key={payment.id}
                    className="flex items-center justify-between gap-4"
                  >
                    <span className="text-muted-foreground">
                      {payment.method_label ?? payment.method}
                      {payment.paid_at
                        ? ` · ${formatDateTime(payment.paid_at)}`
                        : ""}
                    </span>
                    <span className="tabular-nums">
                      {formatCurrency(Number(payment.amount))}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div className="space-y-1 border-t pt-4 text-sm">
            <div className="flex items-center justify-between">
              <span className="text-muted-foreground">
                {t("invoice.paid_amount")}
              </span>
              <span className="tabular-nums">
                {formatCurrency(Number(invoice.paid_amount ?? 0))}
              </span>
            </div>
            {Number(invoice.outstanding_amount ?? 0) > 0 ? (
              <div className="flex items-center justify-between font-medium">
                <span>{t("invoice.outstanding")}</span>
                <span className="tabular-nums text-destructive">
                  {formatCurrency(Number(invoice.outstanding_amount))}
                </span>
              </div>
            ) : null}
          </div>
        </div>
      )}
    </div>
  )
}

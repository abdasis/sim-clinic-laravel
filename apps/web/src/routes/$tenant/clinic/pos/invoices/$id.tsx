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
import { formatCurrency } from "../components/format.ts"

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

interface InvoiceData {
  id: number
  invoice_number: string
  subtotal: string
  payment_status: string
  created_at?: string
  patient_name?: string | null
  cashier_name?: string | null
  clinic_name?: string | null
  items: InvoiceItem[]
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
    <div>
      <div className="print:hidden">
        <ClinicBreadcrumb
          items={[
            { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
            { label: t("clinic.clinic") },
            { label: t("pos.title"), to: "/$tenant/clinic/pos", params: { tenant } },
            { label: t("invoice.title") },
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
        </div>
      )}
    </div>
  )
}

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency, formatDateTime } from "#/lib/format.ts"

export interface PaymentRow {
  id: number
  method: string
  method_label?: string | null
  amount: string | number
  paid_at?: string | null
}

interface PaymentHistoryProps {
  payments: PaymentRow[]
}

/** Cicilan terbaru di atas — yang baru dicatat paling sering dicek ulang. */
export function PaymentHistory({ payments }: PaymentHistoryProps) {
  const { t } = useTrans()

  if (payments.length === 0) {
    return (
      <p className="py-6 text-center text-sm text-muted-foreground">
        {t("pos.no_payments")}
      </p>
    )
  }

  const sorted = [...payments].sort((a, b) =>
    (b.paid_at ?? "").localeCompare(a.paid_at ?? ""),
  )

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t("pos.paid_at")}</TableHead>
          <TableHead>{t("pos.method")}</TableHead>
          <TableHead className="text-right">{t("pos.amount")}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {sorted.map((payment) => (
          <TableRow key={payment.id}>
            <TableCell className="tabular-nums whitespace-nowrap text-muted-foreground">
              {formatDateTime(payment.paid_at)}
            </TableCell>
            <TableCell>{payment.method_label ?? payment.method}</TableCell>
            <TableCell className="text-right font-medium tabular-nums">
              {formatCurrency(Number(payment.amount))}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

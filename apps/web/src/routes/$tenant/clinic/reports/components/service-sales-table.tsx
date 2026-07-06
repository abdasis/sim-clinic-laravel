import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export interface ServiceSalesRow {
  service_id: number
  service_name: string
  qty_sold: number
  revenue: string
}

export function ServiceSalesTable({ rows }: { rows: ServiceSalesRow[] }) {
  const { t } = useTrans()

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t("report.service_name")}</TableHead>
          <TableHead>{t("report.qty_sold")}</TableHead>
          <TableHead>{t("report.revenue")}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.map((row) => (
          <TableRow key={row.service_id}>
            <TableCell>{row.service_name}</TableCell>
            <TableCell>{row.qty_sold}</TableCell>
            <TableCell>{row.revenue}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

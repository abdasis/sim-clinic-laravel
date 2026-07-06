import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export interface ProductSalesRow {
  product_id: number
  product_name: string
  qty_sold: number
  revenue: string
}

export function ProductSalesTable({ rows }: { rows: ProductSalesRow[] }) {
  const { t } = useTrans()

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t("report.product_name")}</TableHead>
          <TableHead>{t("report.qty_sold")}</TableHead>
          <TableHead>{t("report.revenue")}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.map((row) => (
          <TableRow key={row.product_id}>
            <TableCell>{row.product_name}</TableCell>
            <TableCell>{row.qty_sold}</TableCell>
            <TableCell>{row.revenue}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

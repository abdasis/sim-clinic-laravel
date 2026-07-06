import { useQuery } from "@tanstack/react-query"
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

interface Movement {
  id: number
  created_at: string
  type: string
  type_label: string
  quantity: number
  balance_after: number
  note: string | null
}

interface Props {
  tenant: string
  productId: string
}

export function StockMovementHistory({ tenant, productId }: Props) {
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["stock-movements", tenant, productId],
    queryFn: () =>
      apiGet<{ data: Movement[] }>(
        `/${tenant}/clinic/products/${productId}/stock-movements`,
      ),
    enabled: !!productId,
  })

  const rows = data?.data ?? []

  return (
    <div className="mt-8">
      <h2 className="mb-3 text-lg font-semibold">{t("inventory.history")}</h2>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t("inventory.created_at")}</TableHead>
            <TableHead>{t("inventory.type")}</TableHead>
            <TableHead>{t("inventory.quantity")}</TableHead>
            <TableHead>{t("inventory.balance_after")}</TableHead>
            <TableHead>{t("inventory.note")}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={5} className="text-center text-muted-foreground">
                {t("general.loading")}
              </TableCell>
            </TableRow>
          ) : rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="text-center text-muted-foreground">
                {t("general.empty")}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row) => (
              <TableRow key={row.id}>
                <TableCell>{row.created_at}</TableCell>
                <TableCell>{row.type_label}</TableCell>
                <TableCell>{row.quantity}</TableCell>
                <TableCell>{row.balance_after}</TableCell>
                <TableCell>{row.note ?? "-"}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  )
}

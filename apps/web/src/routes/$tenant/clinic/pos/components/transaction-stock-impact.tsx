import { useQuery } from "@tanstack/react-query"

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"

interface Movement {
  id: number
  created_at: string | null
  type: string
  type_label: string
  quantity: number
  balance_after: number
}

interface TransactionStockImpactProps {
  tenant: string
  transactionId: string
}

/**
 * Mutasi stok yang lahir dari transaksi ini — penjualannya dan, bila pernah
 * dibatalkan, pengembaliannya. Ditanam di halaman detail, bukan jadi route
 * sendiri: yang dicari selalu dalam konteks transaksinya.
 */
export function TransactionStockImpact({
  tenant,
  transactionId,
}: TransactionStockImpactProps) {
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["transaction-stock-movements", tenant, transactionId],
    queryFn: () =>
      apiGet<{ data: Movement[] }>(
        `/${tenant}/clinic/transactions/${transactionId}/stock-movements`,
      ),
    select: (res) => res.data,
    // Modul inventaris hanya untuk admin; peran lain akan kena 403.
    retry: false,
  })

  if (isLoading) return <Skeleton className="h-24 w-full" />

  if (!data || data.length === 0) {
    return (
      <EmptyState
        className="py-8"
        illustration="products"
        title={t("inventory.empty_movements")}
        description={t("inventory.empty_movements_desc")}
      />
    )
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t("inventory.created_at")}</TableHead>
          <TableHead>{t("inventory.type")}</TableHead>
          <TableHead className="text-right">{t("inventory.quantity")}</TableHead>
          <TableHead className="text-right">
            {t("inventory.balance_after")}
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {data.map((movement) => (
          <TableRow key={movement.id}>
            <TableCell className="tabular-nums whitespace-nowrap text-muted-foreground">
              {formatDateTime(movement.created_at)}
            </TableCell>
            <TableCell>{movement.type_label}</TableCell>
            <TableCell className="text-right font-medium tabular-nums">
              {movement.type === "rollback" ? "+" : "−"}
              {movement.quantity}
            </TableCell>
            <TableCell className="text-right tabular-nums">
              {movement.balance_after}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

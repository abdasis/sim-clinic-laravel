import { useInfiniteQuery } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import { MedicineBottle01Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { formatCurrency, formatDate } from "#/lib/format.ts"
import type { PurchaseRow } from "./record-types.ts"

interface PurchasesResponse {
  data: PurchaseRow[]
  meta: { current_page: number; last_page: number; total: number }
}

export function purchasesKey(tenant: string, patientId: string) {
  return ["patient-purchases", tenant, patientId] as const
}

/**
 * Kueri dipisah supaya halaman induknya bisa ikut menghitung ringkasannya
 * dari data yang sama, tanpa mengambilnya dua kali.
 */
export function usePatientPurchases(tenant: string, patientId: string) {
  return useInfiniteQuery({
    queryKey: purchasesKey(tenant, patientId),
    initialPageParam: 1,
    queryFn: ({ pageParam }) =>
      apiGet<PurchasesResponse>(
        `/${tenant}/clinic/patients/${patientId}/purchases`,
        { page: pageParam },
      ),
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
  })
}

interface PurchaseHistoryProps {
  rows: PurchaseRow[]
  isLoading: boolean
  hasNextPage: boolean
  isFetchingNextPage: boolean
  onLoadMore: () => void
}

/**
 * Riwayat pembelian produk pasien, terbaru dulu.
 *
 * Berdiri sendiri di samping tabel kunjungan karena tidak semua pembelian
 * berpapasan dengan kunjungan: pasien boleh menebus skincare tanpa treatment
 * apa pun hari itu. Yang perlu dibaca dokter tetap sama — apa yang sedang
 * dipakai pasien di rumah — jadi pembelian seperti itu tidak boleh hilang
 * hanya karena tidak punya baris kunjungan untuk ditempeli.
 */
export function PurchaseHistory({
  rows,
  isLoading,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
}: PurchaseHistoryProps) {
  const { t } = useTrans()

  if (isLoading) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-14 w-full rounded-lg" />
        <Skeleton className="h-14 w-full rounded-lg" />
      </div>
    )
  }

  if (rows.length === 0) {
    return (
      <Card className="border-dashed">
        <CardContent className="py-8">
          <EmptyState
            illustration="products"
            title={t("medical_record.purchases_empty")}
            description={t("medical_record.purchases_empty_desc")}
          />
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="space-y-2">
      {rows.map((row) => (
        <Card
          key={row.transaction_id}
          className="gap-0 py-0 transition-colors hover:border-border"
        >
          <CardContent className="flex flex-wrap items-start gap-3 p-3">
            <span
              aria-hidden
              className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted"
            >
              <HugeiconsIcon
                icon={MedicineBottle01Icon}
                strokeWidth={2}
                className="size-4 text-muted-foreground"
              />
            </span>

            <div className="min-w-0 flex-1 space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs tabular-nums text-muted-foreground">
                  {formatDate(row.purchased_at)}
                </span>
                {/*
                  Pembelian yang berdiri sendiri ditandai, bukan disamarkan:
                  dokter perlu tahu bedanya antara produk yang ditebus saat
                  kunjungan dan yang dibeli terpisah.
                */}
                {row.linked_to_visit ? null : (
                  <Badge variant="outline" className="font-normal">
                    {t("medical_record.purchase_standalone")}
                  </Badge>
                )}
                {row.invoice_number ? (
                  <span className="text-xxs text-muted-foreground/70">
                    {row.invoice_number}
                  </span>
                ) : null}
              </div>

              <ul className="space-y-0.5">
                {row.items.map((item, index) => (
                  <li
                    key={`${row.transaction_id}-${index}`}
                    className="flex flex-wrap items-baseline justify-between gap-2 text-sm"
                  >
                    <span className="min-w-0 text-pretty">
                      {item.name}
                      {item.qty > 1 ? (
                        <span className="ms-1.5 text-xs tabular-nums text-muted-foreground">
                          ×{item.qty}
                        </span>
                      ) : null}
                    </span>
                    <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                      {formatCurrency(item.subtotal)}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          </CardContent>
        </Card>
      ))}

      {hasNextPage ? (
        <div className="flex justify-center pt-1">
          <Button
            variant="outline"
            size="sm"
            onClick={onLoadMore}
            disabled={isFetchingNextPage}
          >
            {isFetchingNextPage
              ? t("general.loading")
              : t("medical_record.load_older_purchases")}
          </Button>
        </div>
      ) : null}
    </div>
  )
}

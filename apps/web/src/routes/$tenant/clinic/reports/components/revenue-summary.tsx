import { Card, CardContent, CardDescription, CardTitle } from "#/components/ui/card.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export interface RevenueData {
  total_revenue: string
  paid_transactions_count: number
}

export function RevenueSummary({ data }: { data: RevenueData }) {
  const { t } = useTrans()

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Card>
        <CardContent>
          <CardDescription>{t("report.total_revenue")}</CardDescription>
          <CardTitle className="mt-1 text-2xl">{data.total_revenue}</CardTitle>
        </CardContent>
      </Card>
      <Card>
        <CardContent>
          <CardDescription>{t("report.paid_transactions_count")}</CardDescription>
          <CardTitle className="mt-1 text-2xl">
            {data.paid_transactions_count}
          </CardTitle>
        </CardContent>
      </Card>
    </div>
  )
}

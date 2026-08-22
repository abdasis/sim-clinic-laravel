import {
  Coins01Icon,
  Invoice01Icon,
  ChartAverageIcon,
} from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { ReportStatCard } from "./report-stat-card.tsx"

export interface RevenueData {
  /** Dikirim server sebagai desimal berpresisi, mis. "1500000.00". */
  total_revenue: string
  paid_transactions_count: number
}

/**
 * Tiga angka pembuka tab omzet.
 *
 * Nilainya datang sebagai string desimal dari server dan sebelumnya
 * ditampilkan apa adanya — "1500000.00" di layar laporan yang dibaca pemilik
 * klinik. Diformat Rupiah di sini, bukan diserahkan ke pembacanya.
 *
 * Rata-rata per transaksi ikut ditampilkan meski tidak dikirim server: ia
 * turunan dari dua angka yang sudah ada, dan justru itu yang dibandingkan
 * antar periode — total omzet naik karena harga naik atau karena kunjungan
 * bertambah adalah dua cerita yang berbeda.
 */
export function RevenueSummary({ data }: { data: RevenueData }) {
  const { t } = useTrans()

  const revenue = Number(data.total_revenue)
  const total = Number.isFinite(revenue) ? revenue : 0
  const count = data.paid_transactions_count
  const average = count > 0 ? total / count : 0

  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <ReportStatCard
        emphasis
        icon={Coins01Icon}
        accent="bg-primary"
        label={t("report.total_revenue")}
        value={formatCurrency(total)}
        hint={t("report.total_revenue_hint")}
      />
      <ReportStatCard
        icon={Invoice01Icon}
        accent="bg-chart-cat-1"
        label={t("report.paid_transactions")}
        value={String(count)}
        hint={t("report.paid_transactions_count")}
      />
      <ReportStatCard
        icon={ChartAverageIcon}
        accent="bg-chart-cat-4"
        label={t("report.average_transaction")}
        value={formatCurrency(average)}
        hint={t("report.average_transaction_hint")}
      />
    </div>
  )
}

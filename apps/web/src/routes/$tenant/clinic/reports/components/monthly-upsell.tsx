import { HugeiconsIcon } from "@hugeicons/react"
import { ShoppingBag03Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { CommissionRow } from "./monthly-types.ts"

/**
 * Blok upselling: omzet yang ditawarkan tiap staf, posisinya terhadap target,
 * dan komisi yang lahir darinya.
 *
 * Dipisah dari blok fee karena pertanyaannya berbeda. Blok fee menjawab
 * "berapa yang dibayarkan"; blok ini menjawab "kenapa segitu" — dan untuk yang
 * belum dapat komisi, "kurang berapa lagi". Selama keduanya menyatu jadi satu
 * nominal, admin tidak punya apa pun untuk ditunjukkan ke terapis yang
 * menanyakan komisinya.
 */
export function MonthlyUpsell({ rows }: { rows: CommissionRow[] }) {
  const { t } = useTrans()

  // Yang tidak menawarkan apa pun tidak punya cerita upselling; menampilkannya
  // sebagai deretan nol hanya memanjangkan tabel.
  const sellers = rows
    .filter((row) => row.revenue > 0)
    .sort((a, b) => b.revenue - a.revenue)

  const total = sellers.reduce((sum, row) => sum + row.commission_amount, 0)

  return (
    <section className="flex flex-col overflow-hidden rounded-lg border border-border/60 bg-card">
      <header className="flex items-center gap-2 border-b border-chart-cat-3/25 bg-chart-cat-3/12 px-3 py-2">
        <span
          aria-hidden
          className="flex size-5 shrink-0 items-center justify-center rounded bg-chart-cat-3"
        >
          <HugeiconsIcon
            icon={ShoppingBag03Icon}
            strokeWidth={2.2}
            className="size-3 text-white"
          />
        </span>
        <h3 className="text-sm font-semibold">{t("report.upsell_title")}</h3>
      </header>

      {sellers.length === 0 ? (
        <p className="px-3 py-6 text-center text-xs text-muted-foreground">
          {t("report.no_upsell")}
        </p>
      ) : (
        <ul className="divide-y divide-border/50">
          {sellers.map((row) => (
            <SellerRow key={row.therapist_id} row={row} />
          ))}
        </ul>
      )}

      <footer className="flex items-center justify-between gap-3 border-t bg-muted/40 px-3 py-2">
        <span className="text-xs font-medium">{t("report.total_upsell")}</span>
        <span className="text-sm font-semibold tabular-nums">
          {formatCurrency(total)}
        </span>
      </footer>

      <p className="border-t border-border/40 px-3 py-2 text-2xs leading-relaxed text-muted-foreground">
        {t("report.upsell_note")}
      </p>
    </section>
  )
}

function SellerRow({ row }: { row: CommissionRow }) {
  const { t } = useTrans()

  const target = row.upsell_target
  const reached = row.upsell_reached
  // Tanpa target, batangnya tidak punya skala — yang ditampilkan omzetnya saja.
  const progress =
    target === null || target <= 0
      ? null
      : Math.min(100, (row.revenue / target) * 100)
  const short = target === null ? 0 : Math.max(0, target - row.revenue)

  return (
    <li className="flex flex-col gap-1.5 px-3 py-2.5">
      <div className="flex items-baseline justify-between gap-3">
        <span className="min-w-0 truncate text-sm font-medium">
          {row.therapist_name}
        </span>
        <span className="shrink-0 text-sm font-semibold tabular-nums">
          {row.commission_amount > 0 ? formatCurrency(row.commission_amount) : "—"}
        </span>
      </div>

      <div className="flex items-center gap-2">
        {/* Batangnya memberi jawaban sebelum angkanya dibaca: hijau berarti
            sudah, abu berarti belum, panjangnya seberapa jauh. */}
        <div
          className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
          role="img"
          aria-label={
            target === null
              ? t("report.upsell_no_target")
              : `${t("report.upsell_progress")}: ${formatCurrency(row.revenue)} ${t("report.upsell_of_target").replace(":amount", formatCurrency(target))}`
          }
        >
          {progress !== null ? (
            <div
              className={cn(
                "h-full rounded-full transition-[width] duration-300 ease-out",
                reached ? "bg-primary" : "bg-muted-foreground/45",
              )}
              style={{ width: `${progress}%` }}
            />
          ) : null}
        </div>

        <span className="shrink-0 text-xs tabular-nums">
          {formatCurrency(row.revenue)}
        </span>
      </div>

      <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-2xs text-muted-foreground">
        {target === null ? (
          <span>{t("report.upsell_no_target")}</span>
        ) : reached ? (
          <>
            <span className="font-medium text-primary">
              {t("report.upsell_reached")}
            </span>
            <span aria-hidden>·</span>
            <span className="tabular-nums">
              {t("report.upsell_of_target").replace(
                ":amount",
                formatCurrency(target),
              )}
            </span>
          </>
        ) : (
          <>
            <span className="tabular-nums">
              {t("report.upsell_short").replace(
                ":amount",
                formatCurrency(short),
              )}
            </span>
            <span aria-hidden>·</span>
            <span className="tabular-nums">
              {t("report.upsell_of_target").replace(
                ":amount",
                formatCurrency(target),
              )}
            </span>
          </>
        )}

        {row.upsell_percent !== null ? (
          <>
            <span aria-hidden>·</span>
            <span className="tabular-nums">{formatPercent(row.upsell_percent)}</span>
          </>
        ) : null}
      </div>
    </li>
  )
}

/** "5" jadi "5%", "7,5" tetap "7,5%" — tanpa nol di belakang koma. */
function formatPercent(percent: number): string {
  return `${percent.toLocaleString("id-ID", { maximumFractionDigits: 2 })}%`
}

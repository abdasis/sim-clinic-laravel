import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"

/** Sisa jatah kirim hari ini, dengan tanda saat sudah mepet. */
export function QuotaCard({
  quota,
}: {
  quota: { limit: number; used: number; remaining: number }
}) {
  const { t } = useTrans()
  const share = quota.limit > 0 ? quota.used / quota.limit : 0
  const spent = quota.remaining === 0
  const tight = !spent && share >= 0.8

  return (
    <div
      className={cn(
        "rounded-lg border border-border bg-card p-3 transition-colors",
        tight && "border-amber-500/50",
        spent && "border-destructive/50",
      )}
    >
      <p className="text-xs text-muted-foreground">{t("broadcast.quota_today")}</p>
      <p
        className={cn(
          "mt-1 text-lg font-semibold tabular-nums",
          tight && "text-amber-600",
          spent && "text-destructive",
        )}
      >
        {quota.remaining}
        <span className="ml-1 text-xs font-normal text-muted-foreground">
          / {quota.limit}
        </span>
      </p>
      <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-muted">
        <div
          className={cn(
            "h-full rounded-full transition-all",
            spent ? "bg-destructive" : tight ? "bg-amber-500" : "bg-primary",
          )}
          style={{ width: `${Math.min(100, Math.round(share * 100))}%` }}
        />
      </div>
    </div>
  )
}

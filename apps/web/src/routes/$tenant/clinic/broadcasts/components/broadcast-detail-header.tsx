import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon, SentIcon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { BroadcastDetail } from "./broadcast-types.ts"

interface BroadcastDetailHeaderProps {
  broadcast: BroadcastDetail
  cloudReady: boolean
  templateInfo: string | null
  statusPending: boolean
  sendAllPending: boolean
  onPause: () => void
  onResume: () => void
  onCancel: () => void
  onSendAll: () => void
  onDelete: () => void
}

export function BroadcastDetailHeader({
  broadcast,
  cloudReady,
  templateInfo,
  statusPending,
  sendAllPending,
  onPause,
  onResume,
  onCancel,
  onSendAll,
  onDelete,
}: BroadcastDetailHeaderProps) {
  const { t } = useTrans()

  return (
    <>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold tracking-tight">{broadcast.title}</h1>
          <p className="text-sm text-muted-foreground">
            {broadcast.kind_label ? `${broadcast.kind_label} · ` : ""}
            {broadcast.status_label ? `${broadcast.status_label} · ` : ""}
            {broadcast.audience_label} ·{" "}
            {t("broadcast.progress")
              .replace(":sent", String(broadcast.recipients_sent))
              .replace(":total", String(broadcast.recipients_total))}
            {templateInfo ? ` · ${templateInfo}` : ""}
          </p>
          {/* Angka gagal dulu tidak pernah sampai ke layar, jadi campaign
              yang separuh hangus terbaca sama dengan yang mulus. */}
          {broadcast.recipients_failed > 0 ? (
            <p className="mt-1 text-sm font-medium text-destructive">
              {t("broadcast.failed_summary").replace(
                ":failed",
                String(broadcast.recipients_failed),
              )}
            </p>
          ) : null}
        </div>

        <div className="flex flex-wrap gap-2">
          {broadcast.status === "sending" ? (
            <>
              <Button variant="outline" onClick={onPause} disabled={statusPending}>
                {t("broadcast.pause")}
              </Button>
              <Button
                variant="outline"
                className="text-destructive"
                onClick={onCancel}
                disabled={statusPending}
              >
                {t("broadcast.cancel_campaign")}
              </Button>
            </>
          ) : null}
          {broadcast.status === "paused" ? (
            <>
              <Button onClick={onResume} disabled={statusPending}>
                {t("broadcast.resume")}
              </Button>
              <Button
                variant="outline"
                className="text-destructive"
                onClick={onCancel}
                disabled={statusPending}
              >
                {t("broadcast.cancel_campaign")}
              </Button>
            </>
          ) : null}
          {/* Yang gagal ikut bisa dikirim ulang; tanpa itu campaign yang
              seluruhnya gagal tidak punya jalan keluar sama sekali. */}
          {cloudReady &&
          broadcast.status !== "sending" &&
          broadcast.status !== "paused" &&
          broadcast.status !== "cancelled" &&
          (broadcast.recipients_pending > 0 || broadcast.recipients_failed > 0) ? (
            <Button onClick={onSendAll} disabled={sendAllPending} className="gap-1.5">
              <HugeiconsIcon icon={SentIcon} strokeWidth={2} className="size-4" />
              {sendAllPending ? t("general.loading") : t("broadcast.send_all")}
            </Button>
          ) : null}
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="icon"
                aria-label={t("general.delete")}
                className="text-muted-foreground hover:text-destructive"
                onClick={onDelete}
              >
                <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} className="size-4" />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("general.delete")}</TooltipContent>
          </Tooltip>
        </div>
      </div>

      {/* Tanpa Cloud API yang siap, tombol "Kirim Semua" tidak muncul.
          Alasannya disebutkan supaya admin tidak mengira fiturnya rusak. */}
      {!cloudReady && broadcast.recipients_pending > 0 ? (
        <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
          {t("broadcast.cloud_not_ready")}
        </p>
      ) : null}
    </>
  )
}

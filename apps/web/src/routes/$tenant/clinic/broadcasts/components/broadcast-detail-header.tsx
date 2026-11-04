import { Link } from "@tanstack/react-router"
import type { ReactNode } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  Delete02Icon,
  PauseIcon,
  PencilEdit01Icon,
  PlayIcon,
  SentIcon,
} from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { BroadcastDetail } from "./broadcast-types.ts"

interface BroadcastDetailHeaderProps {
  tenant: string
  broadcast: BroadcastDetail
  gatewayReady: boolean
  quotaSpent: boolean
  templateInfo: string | null
  statusPending: boolean
  sendAllPending: boolean
  onPause: () => void
  onResume: () => void
  onCancel: () => void
  onSendAll: () => void
  onDelete: () => void
}

/** Tombol disabled tidak memicu tooltip Radix, jadi pembungkus span yang jadi pemicunya. */
function QuotaHint({
  blocked,
  hint,
  children,
}: {
  blocked: boolean
  hint: string
  children: ReactNode
}) {
  if (!blocked) return <>{children}</>

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="inline-flex">{children}</span>
      </TooltipTrigger>
      <TooltipContent>{hint}</TooltipContent>
    </Tooltip>
  )
}

export function BroadcastDetailHeader({
  tenant,
  broadcast,
  gatewayReady,
  quotaSpent,
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

  // Kendali jeda/mulai hanya relevan selama campaign belum berakhir.
  const hasControls =
    broadcast.status === "ready" ||
    broadcast.status === "sending" ||
    broadcast.status === "paused"
  const canStart = broadcast.status === "ready" || broadcast.status === "paused"
  const hasPending =
    broadcast.recipients_pending + broadcast.recipients_failed > 0
  const startBlocked =
    statusPending ||
    quotaSpent ||
    (broadcast.status === "ready" && (!gatewayReady || !hasPending))

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
          {hasControls ? (
            <>
              <QuotaHint blocked={quotaSpent} hint={t("broadcast.quota_spent_hint")}>
                <Button
                  onClick={
                    broadcast.status === "paused" ? onResume : onSendAll
                  }
                  disabled={!canStart || startBlocked}
                  className="gap-1.5"
                >
                  <HugeiconsIcon
                    icon={PlayIcon}
                    strokeWidth={2}
                    className="size-4"
                  />
                  {broadcast.status === "paused"
                    ? t("broadcast.resume")
                    : t("broadcast.start")}
                </Button>
              </QuotaHint>
              <Button
                variant="outline"
                onClick={onPause}
                disabled={broadcast.status !== "sending" || statusPending}
                className="gap-1.5"
              >
                <HugeiconsIcon
                  icon={PauseIcon}
                  strokeWidth={2}
                  className="size-4"
                />
                {t("broadcast.pause")}
              </Button>
              {broadcast.status !== "ready" ? (
                <Button
                  variant="outline"
                  className="text-destructive"
                  onClick={onCancel}
                  disabled={statusPending}
                >
                  {t("broadcast.cancel_campaign")}
                </Button>
              ) : null}
            </>
          ) : null}
          {/* Yang gagal ikut bisa dikirim ulang; tanpa itu campaign yang
              seluruhnya gagal tidak punya jalan keluar sama sekali. */}
          {gatewayReady &&
          broadcast.status !== "sending" &&
          broadcast.status !== "paused" &&
          broadcast.status !== "ready" &&
          broadcast.status !== "cancelled" &&
          (broadcast.recipients_pending > 0 || broadcast.recipients_failed > 0) ? (
            <QuotaHint blocked={quotaSpent} hint={t("broadcast.quota_spent_hint")}>
              <Button
                onClick={onSendAll}
                disabled={sendAllPending || quotaSpent}
                className="gap-1.5"
              >
                <HugeiconsIcon icon={SentIcon} strokeWidth={2} className="size-4" />
                {sendAllPending ? t("general.loading") : t("broadcast.send_all")}
              </Button>
            </QuotaHint>
          ) : null}
          {broadcast.can_edit ? (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="outline" size="icon" asChild>
                  <Link
                    to="/$tenant/clinic/broadcasts/$id/edit"
                    params={{ tenant, id: String(broadcast.id) }}
                    aria-label={t("broadcast.edit_title")}
                  >
                    <HugeiconsIcon
                      icon={PencilEdit01Icon}
                      strokeWidth={2}
                      className="size-4"
                    />
                  </Link>
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("broadcast.edit_title")}</TooltipContent>
            </Tooltip>
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
      {!gatewayReady && broadcast.recipients_pending > 0 ? (
        <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
          {t("broadcast.gateway_not_ready")}
        </p>
      ) : null}
    </>
  )
}

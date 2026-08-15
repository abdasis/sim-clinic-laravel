import { createFileRoute, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  ArrowTurnBackwardIcon,
  BubbleChatIcon,
  Delete02Icon,
  SentIcon,
} from "@hugeicons/core-free-icons"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "#/components/ui/alert-dialog.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Progress } from "#/components/ui/progress.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiGet, apiPatch, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { useNavigate } from "@tanstack/react-router"
import { cn } from "#/lib/utils.ts"

export const Route = createFileRoute("/$tenant/clinic/broadcasts/$id")({
  component: BroadcastDetailPage,
})

interface Recipient {
  id: number
  name: string
  phone: string
  message: string
  status: string
  status_label: string
  sent_at?: string | null
  error?: string | null
}

interface BroadcastDetail {
  id: number
  title: string
  message: string
  status?: string | null
  status_label?: string | null
  kind_label?: string | null
  audience_label: string
  recipients_total: number
  recipients_sent: number
  recipients_pending: number
  recipients: Recipient[]
}

const STATUS_TONE: Record<string, string> = {
  pending: "border-border/60 text-muted-foreground",
  sent: "border-primary/40 text-primary",
  failed: "border-destructive/40 text-destructive",
  skipped: "border-amber-500/40 text-amber-600 dark:text-amber-400",
}

function BroadcastDetailPage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/broadcasts/$id" })
  const { t } = useTrans()
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [confirmSendAll, setConfirmSendAll] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)

  const queryKey = ["broadcasts", tenant, id]

  const { data, isLoading } = useQuery({
    queryKey,
    queryFn: () =>
      apiGet<{ data: BroadcastDetail; meta: { gateway_ready: boolean } }>(
        `/${tenant}/clinic/broadcasts/${id}`,
      ),
  })

  const broadcast = data?.data
  const gatewayReady = data?.meta.gateway_ready ?? false

  const mark = useMutation({
    mutationFn: ({ recipient, status }: { recipient: number; status: string }) =>
      apiPatch(`/${tenant}/clinic/broadcasts/${id}/recipients/${recipient}`, {
        status,
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey }),
    onError: (err: ApiError) => toast.error(err.message),
  })

  const changeStatus = useMutation({
    mutationFn: (status: "sending" | "paused" | "cancelled") =>
      apiPatch(`/${tenant}/clinic/broadcasts/${id}/status`, { status }),
    onSuccess: () => qc.invalidateQueries({ queryKey }),
    onError: (err: ApiError) => toast.error(err.message),
  })

  const sendAll = useMutation({
    mutationFn: () =>
      apiPost<{ data: { sent: number; failed: number }; meta: { message: string } }>(
        `/${tenant}/clinic/broadcasts/${id}/send`,
      ),
    onSuccess: (res) => {
      toast.success(res.meta.message)
      qc.invalidateQueries({ queryKey })
      setConfirmSendAll(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setConfirmSendAll(false)
    },
  })

  const remove = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/broadcasts/${id}`),
    onSuccess: () => {
      toast.success(t("broadcast.deleted"))
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      navigate({ to: "/$tenant/clinic/broadcasts", params: { tenant } })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  /**
   * Jalur manual: buka WhatsApp dengan pesan terisi, lalu catat terkirim.
   * Tab dibuka lebih dulu — pemblokir popup menahan window.open yang datang
   * setelah await.
   */
  const sendManual = (recipient: Recipient) => {
    window.open(
      `https://wa.me/${recipient.phone}?text=${encodeURIComponent(recipient.message)}`,
      "_blank",
      "noopener",
    )
    mark.mutate({ recipient: recipient.id, status: "sent" })
  }

  if (isLoading || !broadcast) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  const progress =
    broadcast.recipients_total > 0
      ? Math.round((broadcast.recipients_sent / broadcast.recipients_total) * 100)
      : 0

  return (
    <div className="space-y-4">
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/broadcasts", params: { tenant } },
          {
            label: t("broadcast.title"),
            to: "/$tenant/clinic/broadcasts",
            params: { tenant },
          },
          { label: broadcast.title },
        ]}
      />

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold">{broadcast.title}</h1>
          <p className="text-sm text-muted-foreground">
            {broadcast.kind_label ? `${broadcast.kind_label} · ` : ""}
            {broadcast.status_label ? `${broadcast.status_label} · ` : ""}
            {broadcast.audience_label} ·{" "}
            {t("broadcast.progress")
              .replace(":sent", String(broadcast.recipients_sent))
              .replace(":total", String(broadcast.recipients_total))}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {broadcast.status === "sending" ? (
            <>
              <Button variant="outline" onClick={() => changeStatus.mutate("paused")} disabled={changeStatus.isPending}>
                {t("broadcast.pause")}
              </Button>
              <Button variant="outline" className="text-destructive" onClick={() => changeStatus.mutate("cancelled")} disabled={changeStatus.isPending}>
                {t("broadcast.cancel_campaign")}
              </Button>
            </>
          ) : null}
          {broadcast.status === "paused" ? (
            <>
              <Button onClick={() => changeStatus.mutate("sending")} disabled={changeStatus.isPending}>
                {t("broadcast.resume")}
              </Button>
              <Button variant="outline" className="text-destructive" onClick={() => changeStatus.mutate("cancelled")} disabled={changeStatus.isPending}>
                {t("broadcast.cancel_campaign")}
              </Button>
            </>
          ) : null}
          {gatewayReady && broadcast.status !== "sending" && broadcast.status !== "paused" && broadcast.status !== "cancelled" && broadcast.recipients_pending > 0 ? (
            <Button
              onClick={() => setConfirmSendAll(true)}
              disabled={sendAll.isPending}
              className="gap-1.5"
            >
              <HugeiconsIcon icon={SentIcon} strokeWidth={2} className="size-4" />
              {sendAll.isPending
                ? t("general.loading")
                : t("broadcast.send_all")}
            </Button>
          ) : null}
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="icon"
                aria-label={t("general.delete")}
                className="text-muted-foreground hover:text-destructive"
                onClick={() => setConfirmDelete(true)}
              >
                <HugeiconsIcon
                  icon={Delete02Icon}
                  strokeWidth={2}
                  className="size-4"
                />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("general.delete")}</TooltipContent>
          </Tooltip>
        </div>
      </div>

      <Progress value={progress} className="h-2" />

      {broadcast.recipients.length === 0 ? (
        <p className="rounded-md border border-dashed border-border/60 px-4 py-8 text-center text-sm text-muted-foreground">
          {t("broadcast.no_recipients")}
        </p>
      ) : (
        <ul className="divide-y divide-border/50 rounded-lg border border-border/50 bg-card">
          {broadcast.recipients.map((recipient) => (
            <li
              key={recipient.id}
              className="flex flex-wrap items-center gap-3 p-3 transition-colors hover:bg-muted/30"
            >
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate text-sm font-medium">{recipient.name}</p>
                  <span className="text-xs text-muted-foreground tabular-nums">
                    +{recipient.phone}
                  </span>
                </div>
                <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                  {recipient.message}
                </p>
                {recipient.error ? (
                  <p className="mt-0.5 text-xs text-destructive">{recipient.error}</p>
                ) : null}
              </div>

              <Badge
                variant="outline"
                className={cn(
                  "shrink-0 font-normal",
                  STATUS_TONE[recipient.status] ?? "",
                )}
              >
                {recipient.status_label}
              </Badge>

              {recipient.status === "pending" || recipient.status === "failed" ? (
                <div className="flex shrink-0 gap-1.5">
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Button
                        size="sm"
                        className="gap-1.5"
                        onClick={() => sendManual(recipient)}
                      >
                        <HugeiconsIcon
                          icon={BubbleChatIcon}
                          strokeWidth={2}
                          className="size-3.5"
                        />
                        {t("broadcast.send_one")}
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>{t("broadcast.send_one")}</TooltipContent>
                  </Tooltip>
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Button
                        size="sm"
                        variant="ghost"
                        className="text-muted-foreground"
                        onClick={() =>
                          mark.mutate({ recipient: recipient.id, status: "skipped" })
                        }
                      >
                        {t("broadcast.status.skipped")}
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>{t("broadcast.mark_skipped")}</TooltipContent>
                  </Tooltip>
                </div>
              ) : (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      size="icon"
                      variant="ghost"
                      className="size-7 shrink-0 text-muted-foreground"
                      aria-label={t("broadcast.mark_pending")}
                      onClick={() =>
                        mark.mutate({ recipient: recipient.id, status: "pending" })
                      }
                    >
                      <HugeiconsIcon
                        icon={ArrowTurnBackwardIcon}
                        strokeWidth={2}
                        className="size-3.5"
                      />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{t("broadcast.mark_pending")}</TooltipContent>
                </Tooltip>
              )}
            </li>
          ))}
        </ul>
      )}

      <AlertDialog open={confirmSendAll} onOpenChange={setConfirmSendAll}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("broadcast.send_all")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("broadcast.send_all_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={sendAll.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={sendAll.isPending}
              onClick={(event) => {
                event.preventDefault()
                sendAll.mutate()
              }}
            >
              {sendAll.isPending ? t("general.loading") : t("broadcast.send_all")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={confirmDelete} onOpenChange={setConfirmDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("general.delete")} — {broadcast.title}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("broadcast.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={remove.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={remove.isPending}
              onClick={(event) => {
                event.preventDefault()
                remove.mutate()
              }}
            >
              {remove.isPending ? t("general.loading") : t("general.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

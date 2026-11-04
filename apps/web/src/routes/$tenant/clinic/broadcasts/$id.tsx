import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Alert02Icon } from "@hugeicons/core-free-icons"

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
import { Alert, AlertDescription, AlertTitle } from "#/components/ui/alert.tsx"
import { Progress } from "#/components/ui/progress.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiGet, apiPatch, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import { BroadcastDetailHeader } from "./components/broadcast-detail-header.tsx"
import { BroadcastRecipients } from "./components/broadcast-recipients.tsx"
import type { BroadcastDetail, Recipient } from "./components/broadcast-types.ts"

export const Route = createFileRoute("/$tenant/clinic/broadcasts/$id")({
  component: BroadcastDetailPage,
})

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
      apiGet<{
        data: BroadcastDetail
        meta: {
          gateway_ready: boolean
          quota?: { limit: number; used: number; remaining: number }
          quota_spent?: boolean
        }
      }>(
        `/${tenant}/clinic/broadcasts/${id}`,
      ),
  })

  const broadcast = data?.data
  useBreadcrumbTail(broadcast?.title)
  const gatewayReady = data?.meta.gateway_ready ?? false
  const quotaSpent = data?.meta.quota_spent === true
  const templateBody = broadcast?.template?.body?.trim()
  const templateInfo = templateBody
    ? templateBody.length > 60
      ? `${templateBody.slice(0, 60)}…`
      : templateBody
    : null

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
      {/* Dijeda gateway terbaca sama dengan dijeda orang kalau sebabnya tidak
          disebut — yang tersisa cuma campaign berhenti tanpa petunjuk apakah
          ada yang perlu dibereskan dulu sebelum dilanjutkan. */}
      {broadcast.status === "paused" && broadcast.paused_reason ? (
        <Alert variant="destructive">
          <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} />
          <AlertTitle>{t("broadcast.paused_by_gateway")}</AlertTitle>
          <AlertDescription>
            <p>{t("broadcast.paused_by_gateway_desc")}</p>
            {broadcast.resume_after ? (
              <p className="mt-1 font-medium">
                {t("broadcast.resume_after").replace(
                  ":time",
                  formatDateTime(broadcast.resume_after),
                )}
              </p>
            ) : null}
            <p className="mt-1.5 font-mono text-xs break-words text-destructive/80">
              {t("broadcast.paused_gateway_said")}: {broadcast.paused_reason}
            </p>
          </AlertDescription>
        </Alert>
      ) : null}

      <BroadcastDetailHeader
        tenant={tenant}
        broadcast={broadcast}
        gatewayReady={gatewayReady}
        quotaSpent={quotaSpent}
        templateInfo={templateInfo}
        statusPending={changeStatus.isPending}
        sendAllPending={sendAll.isPending}
        onPause={() => changeStatus.mutate("paused")}
        onResume={() => changeStatus.mutate("sending")}
        onCancel={() => changeStatus.mutate("cancelled")}
        onSendAll={() => setConfirmSendAll(true)}
        onDelete={() => setConfirmDelete(true)}
      />

      {/* Poster yang ikut terkirim. Ditampilkan supaya admin bisa memastikan
          gambar yang benar yang berangkat sebelum menekan kirim ke ratusan
          nomor — sesudah itu tidak ada jalan menariknya kembali. */}
      {broadcast.image_url ? (
        <a
          href={broadcast.image_url}
          target="_blank"
          rel="noreferrer"
          className="block w-fit rounded-md border border-border/60 p-1 transition-colors hover:border-border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          <img
            src={broadcast.image_url}
            alt={t("broadcast.image")}
            className="max-h-48 rounded object-contain"
          />
        </a>
      ) : null}

      <Progress value={progress} className="h-2" />

      <BroadcastRecipients
        recipients={broadcast.recipients}
        onSendManual={sendManual}
        onMark={(recipient, status) =>
          mark.mutate({ recipient: recipient.id, status })
        }
      />

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

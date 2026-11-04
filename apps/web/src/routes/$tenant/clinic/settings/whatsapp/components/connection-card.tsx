import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon, Logout01Icon, QrCodeIcon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { DeleteSessionDialog } from "./delete-session-dialog.tsx"
import { PairingForm } from "./pairing-form.tsx"
import type { ConnectionState, PairingCode } from "./whatsapp-types.ts"

/**
 * Koneksi WhatsApp: status, QR, dan tindakan sesi.
 *
 * Status ditanya ulang tiap 4 detik selama belum tersambung — begitu admin
 * selesai memindai, layarnya berubah sendiri. Tanyanya berhenti saat
 * tersambung atau saat komponennya dilepas, jadi tidak ada permintaan yang
 * terus berjalan di latar belakang.
 */
export function ConnectionCard({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [pairing, setPairing] = useState<PairingCode | null>(null)
  const [confirmDelete, setConfirmDelete] = useState(false)

  const connection = useQuery({
    queryKey: ["wa-connection", tenant],
    queryFn: () =>
      apiGet<{ data: ConnectionState }>(`/${tenant}/clinic/broadcasts/connection`),
    refetchInterval: (query) => {
      const state = query.state.data?.data
      return state?.available && !state.connected ? 4000 : false
    },
  })

  const prepare = useMutation({
    mutationFn: () =>
      apiPost<{ data: ConnectionState }>(
        `/${tenant}/clinic/broadcasts/connection/prepare`,
        {},
      ),
    onSuccess: (result) => qc.setQueryData(["wa-connection", tenant], result),
    onError: (err: ApiError) => toast.error(err.message),
  })

  const disconnect = useMutation({
    mutationFn: () =>
      apiPost<{ data: ConnectionState }>(
        `/${tenant}/clinic/broadcasts/connection/disconnect`,
        {},
      ),
    onSuccess: (result) => {
      qc.setQueryData(["wa-connection", tenant], result)
      toast.success(t("broadcast.session_disconnected"))
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const logout = useMutation({
    mutationFn: () =>
      apiPost<{ data: ConnectionState }>(
        `/${tenant}/clinic/broadcasts/connection/logout`,
        {},
      ),
    onSuccess: (result) => {
      qc.setQueryData(["wa-connection", tenant], result)
      toast.success(t("settings.whatsapp.logout_success"))
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const state = connection.data?.data
  const busy = disconnect.isPending || logout.isPending

  const deleteButton = state?.provisioned ? (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t("broadcast.delete_session")}
          className="text-muted-foreground hover:text-destructive"
          disabled={busy}
          onClick={() => setConfirmDelete(true)}
        >
          <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} className="size-4" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>{t("broadcast.delete_session_hint")}</TooltipContent>
    </Tooltip>
  ) : null

  return (
    <>
      <Card>
        <CardHeader>
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <CardTitle className="text-base">
                {t("settings.whatsapp.connection_title")}
              </CardTitle>
              <CardDescription className="text-pretty">
                {t("settings.whatsapp.connection_desc")}
              </CardDescription>
            </div>
            {state ? (
              <Badge
                variant={state.connected ? "default" : "outline"}
                className="shrink-0 font-normal"
              >
                {state.connected
                  ? t("broadcast.connected")
                  : t("broadcast.disconnected")}
              </Badge>
            ) : null}
          </div>
        </CardHeader>

        <CardContent>
          {connection.isLoading ? (
            <Skeleton className="h-40 w-full" />
          ) : connection.isError ? (
            <div className="space-y-2 rounded-lg border border-dashed border-border/60 px-4 py-6 text-center">
              <p className="text-sm text-muted-foreground">
                {t("general.load_failed")}
              </p>
              <Button
                variant="outline"
                size="sm"
                onClick={() => void connection.refetch()}
              >
                {t("general.retry")}
              </Button>
            </div>
          ) : !state?.available ? (
            <p className="rounded-lg border border-dashed border-border/60 px-4 py-6 text-center text-sm text-pretty text-muted-foreground">
              {t("settings.whatsapp.unavailable_hint")}
            </p>
          ) : state.connected ? (
            <div className="space-y-3">
              <div className="rounded-lg border border-border/60 bg-muted/30 px-4 py-3">
                <p className="flex items-center gap-2 text-sm font-medium">
                  <span className="size-2 rounded-full bg-primary" aria-hidden="true" />
                  {t("broadcast.connected")}
                </p>
                {state.number ? (
                  <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                    +{state.number}
                  </p>
                ) : null}
                {state.name ? (
                  <p className="text-xs text-muted-foreground">{state.name}</p>
                ) : null}
                <p className="mt-2 text-xs text-pretty text-muted-foreground">
                  {t("settings.whatsapp.connected_hint")}
                </p>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="outline"
                      size="sm"
                      className="gap-1.5 text-muted-foreground hover:text-destructive"
                      disabled={busy}
                      onClick={() => disconnect.mutate()}
                    >
                      <HugeiconsIcon
                        icon={Logout01Icon}
                        strokeWidth={2}
                        className="size-4"
                      />
                      {disconnect.isPending
                        ? t("general.loading")
                        : t("broadcast.disconnect")}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{t("broadcast.disconnect_hint")}</TooltipContent>
                </Tooltip>

                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={busy}
                      onClick={() => logout.mutate()}
                    >
                      {logout.isPending
                        ? t("general.loading")
                        : t("settings.whatsapp.logout")}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>
                    {t("settings.whatsapp.logout_hint")}
                  </TooltipContent>
                </Tooltip>

                {deleteButton}
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              {state.qr ? (
                <img
                  src={state.qr}
                  alt="QR WhatsApp"
                  className="mx-auto size-56 rounded-lg border border-border/50 bg-white p-2"
                />
              ) : (
                <div className="mx-auto flex size-56 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 text-muted-foreground">
                  <HugeiconsIcon icon={QrCodeIcon} strokeWidth={1.5} className="size-10" />
                  <p className="max-w-40 text-center text-xs text-pretty">
                    {t("broadcast.session_needs_start")}
                  </p>
                </div>
              )}

              <p className="text-center text-xs text-pretty text-muted-foreground">
                {state.qr ? t("broadcast.scan_hint") : null}
              </p>

              <div className="flex flex-wrap items-center justify-center gap-2">
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      size="sm"
                      disabled={prepare.isPending}
                      onClick={() => prepare.mutate()}
                    >
                      {prepare.isPending
                        ? t("general.loading")
                        : t("settings.whatsapp.connect")}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>
                    {t("settings.whatsapp.connect_hint")}
                  </TooltipContent>
                </Tooltip>
                {deleteButton}
              </div>

              <PairingForm
                tenant={tenant}
                pairing={pairing}
                onPairing={setPairing}
              />

              {state.error ? (
                <p className="text-center text-xs text-destructive">
                  {t("broadcast.gateway_error")}
                </p>
              ) : null}
            </div>
          )}
        </CardContent>
      </Card>

      <DeleteSessionDialog
        tenant={tenant}
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
      />
    </>
  )
}

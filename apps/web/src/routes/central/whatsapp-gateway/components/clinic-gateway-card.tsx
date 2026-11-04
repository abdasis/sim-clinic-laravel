import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Settings02Icon, WebhookIcon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { WebhookUrl } from "#/components/webhook-url.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { ClinicGatewayDialog } from "./clinic-gateway-dialog.tsx"
import type { ClinicGatewayRow } from "./gateway-types.ts"

interface WebhookResult {
  meta?: { message?: string }
}

export function ClinicGatewayCard({ row }: { row: ClinicGatewayRow }) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)

  const webhook = useMutation({
    mutationFn: () =>
      apiPost<WebhookResult>(
        `/central/whatsapp-gateway/clinics/${row.tenant_id}/webhook`,
      ),
    onSuccess: (result) => {
      toast.success(
        result?.meta?.message ?? t("whatsapp_gateway.webhook_registered"),
      )
      void qc.invalidateQueries({
        queryKey: ["central-whatsapp-gateway-clinics"],
      })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const canRegister = row.api_key_configured && !webhook.isPending

  return (
    <TooltipProvider>
      <div className="rounded-lg border border-border/60 p-3 transition-colors sm:p-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0">
            <p className="truncate font-medium">{row.name}</p>
            <p className="truncate font-mono text-xs text-muted-foreground">
              {row.slug}
            </p>
          </div>

          <div className="flex shrink-0 items-center gap-2">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setOpen(true)}
                >
                  <HugeiconsIcon
                    icon={Settings02Icon}
                    strokeWidth={2}
                    className="size-4"
                    aria-hidden="true"
                  />
                  {t("whatsapp_gateway.manage")}
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                {t("whatsapp_gateway.manage_hint")}
              </TooltipContent>
            </Tooltip>

            <Tooltip>
              <TooltipTrigger asChild>
                <span
                  tabIndex={0}
                  aria-label={t("whatsapp_gateway.webhook_register")}
                  className="inline-flex rounded-md"
                >
                  <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label={t("whatsapp_gateway.webhook_register")}
                    disabled={!canRegister}
                    onClick={() => webhook.mutate()}
                  >
                    <HugeiconsIcon
                      icon={WebhookIcon}
                      strokeWidth={2}
                      className="size-4"
                      aria-hidden="true"
                    />
                  </Button>
                </span>
              </TooltipTrigger>
              <TooltipContent className="max-w-64">
                {row.api_key_configured
                  ? t("whatsapp_gateway.webhook_register")
                  : t("whatsapp_gateway.webhook_register_hint")}
              </TooltipContent>
            </Tooltip>
          </div>
        </div>

        <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
          <StatusCell
            label={t("whatsapp_gateway.api_key")}
            tooltip={t("whatsapp_gateway.api_key_hint")}
            ok={row.api_key_configured}
            okLabel={t("whatsapp_gateway.api_key_configured")}
            missingLabel={t("whatsapp_gateway.api_key_missing")}
          />
          <StatusCell
            label={t("whatsapp_gateway.webhook")}
            tooltip={t("whatsapp_gateway.webhook_hint")}
            ok={row.webhook_configured}
            okLabel={t("whatsapp_gateway.webhook_configured")}
            missingLabel={t("whatsapp_gateway.webhook_missing")}
          />

          <div className="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2.5 py-1.5">
            <dt className="text-xs text-muted-foreground">
              {t("whatsapp_gateway.session")}
            </dt>
            <dd className="truncate font-mono text-xs">
              {row.session || t("whatsapp_gateway.session_empty")}
            </dd>
          </div>

          <div className="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2.5 py-1.5">
            <dt className="text-xs text-muted-foreground">
              {t("whatsapp_gateway.daily_limit")}
            </dt>
            <dd className="tabular-nums">
              {row.daily_message_limit == null
                ? t("whatsapp_gateway.daily_limit_default")
                : row.daily_message_limit.toLocaleString("id-ID")}
            </dd>
          </div>
        </dl>

        <div className="mt-3 space-y-1">
          <p className="text-xs text-muted-foreground">
            {t("whatsapp_gateway.webhook_url_label")}
          </p>
          <WebhookUrl
            url={row.webhook_url}
            copyLabel={t("whatsapp_gateway.copy_webhook_url")}
            copiedLabel={t("whatsapp_gateway.copied")}
            emptyLabel={t("whatsapp_gateway.webhook_url_empty")}
          />
        </div>
      </div>

      <ClinicGatewayDialog row={row} open={open} onOpenChange={setOpen} />
    </TooltipProvider>
  )
}

function StatusCell({
  label,
  tooltip,
  ok,
  okLabel,
  missingLabel,
}: {
  label: string
  tooltip: string
  ok: boolean
  okLabel: string
  missingLabel: string
}) {
  return (
    <div className="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2.5 py-1.5">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd>
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge
              variant={ok ? "secondary" : "outline"}
              tabIndex={0}
              aria-label={`${label}: ${ok ? okLabel : missingLabel}`}
              className="font-normal"
            >
              {ok ? okLabel : missingLabel}
            </Badge>
          </TooltipTrigger>
          <TooltipContent className="max-w-64">{tooltip}</TooltipContent>
        </Tooltip>
      </dd>
    </div>
  )
}

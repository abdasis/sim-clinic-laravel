import { useEffect, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Switch } from "#/components/ui/switch.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { NativeSelect, NativeSelectOption } from "#/components/ui/native-select.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiGet, apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface ToolDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Koneksi WhatsApp lewat WAHA: status terhubung, atau QR untuk dipindai.
 * Selagi belum terhubung, status di-poll tiap 4 detik supaya begitu admin
 * memindai, layarnya langsung berubah tanpa muat ulang.
 */
export function ConnectionDialog({ tenant, open, onOpenChange }: ToolDialogProps) {
  const { t } = useTrans()

  const connection = useQuery({
    queryKey: ["wa-connection", tenant],
    queryFn: () =>
      apiGet<{
        data: {
          available: boolean
          connected: boolean
          number?: string | null
          name?: string | null
          qr?: string | null
          error?: boolean
        }
      }>(`/${tenant}/clinic/broadcasts/connection`),
    enabled: open,
    refetchInterval: (query) =>
      open && query.state.data && !query.state.data.data.connected ? 4000 : false,
  })

  const state = connection.data?.data

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("broadcast.connection")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("broadcast.connection_hint")}
          </DialogDescription>
        </DialogHeader>

        {connection.isLoading ? (
          <Skeleton className="h-48 w-full" />
        ) : !state?.available ? (
          // Belum tersambung ke WAHA sama sekali: entah servernya belum diisi
          // pengelola, entah kliniknya belum menyebut nama sesi.
          <p className="rounded-md border border-dashed border-border/60 px-4 py-6 text-center text-sm text-pretty text-muted-foreground">
            {t("broadcast.waha_session_missing")}
          </p>
        ) : state.connected ? (
          <div className="space-y-2 rounded-md border border-primary/40 bg-primary/5 p-4 text-sm">
            <div className="flex items-center gap-2">
              <span className="size-2 rounded-full bg-primary" aria-hidden="true" />
              <span className="font-medium">{t("broadcast.connected")}</span>
            </div>
            {state.number ? (
              <p className="text-muted-foreground tabular-nums">+{state.number}</p>
            ) : null}
            {state.name ? (
              <p className="text-xs text-muted-foreground">{state.name}</p>
            ) : null}
          </div>
        ) : (
          <div className="space-y-3 text-center">
            <Badge variant="outline" className="font-normal">
              {t("broadcast.disconnected")}
            </Badge>
            {state.qr ? (
              <img
                src={state.qr}
                alt="QR WhatsApp"
                className="mx-auto size-64 rounded-md border border-border/50 bg-white p-2"
              />
            ) : (
              <Skeleton className="mx-auto size-64" />
            )}
            <p className="text-xs text-pretty text-muted-foreground">
              {t("broadcast.scan_hint")}
            </p>
            {state.error ? (
              <p className="text-xs text-destructive">{t("broadcast.waha_error")}</p>
            ) : null}
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}

interface Template {
  id: number
  name: string
  body: string
}

/** Kelola templat pesan yang dipakai broadcast dan pengingat. */
export function TemplatesDialog({ tenant, open, onOpenChange }: ToolDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [name, setName] = useState("")
  const [body, setBody] = useState("")

  const templates = useQuery({
    queryKey: ["message-templates", tenant],
    queryFn: () =>
      apiGet<{ data: Template[] }>(`/${tenant}/clinic/message-templates`),
    enabled: open,
  })

  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["message-templates"] })

  const create = useMutation({
    mutationFn: () =>
      apiPost(`/${tenant}/clinic/message-templates`, { name, body }),
    onSuccess: () => {
      toast.success(t("broadcast.template_saved"))
      setName("")
      setBody("")
      invalidate()
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const remove = useMutation({
    mutationFn: (id: number) =>
      apiDelete(`/${tenant}/clinic/message-templates/${id}`),
    onSuccess: () => {
      toast.success(t("broadcast.template_deleted"))
      invalidate()
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t("broadcast.templates")}</DialogTitle>
          <DialogDescription>
            {"{nama} {klinik} {layanan_terakhir} {tanggal_terakhir} {hari_sejak_kunjungan}"}
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[30dvh] overflow-y-auto">
          {templates.isLoading ? (
            <Skeleton className="h-16 w-full" />
          ) : (templates.data?.data.length ?? 0) === 0 ? (
            <p className="py-4 text-center text-xs text-muted-foreground">
              {t("broadcast.no_templates")}
            </p>
          ) : (
            <ul className="divide-y divide-border/50">
              {templates.data?.data.map((template) => (
                <li key={template.id} className="flex items-start gap-2 py-2">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium">{template.name}</p>
                    <p className="line-clamp-2 text-xs whitespace-pre-line text-muted-foreground">
                      {template.body}
                    </p>
                  </div>
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                        aria-label={t("general.delete")}
                        onClick={() => remove.mutate(template.id)}
                      >
                        <HugeiconsIcon
                          icon={Delete02Icon}
                          strokeWidth={2}
                          className="size-3.5"
                        />
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>{t("general.delete")}</TooltipContent>
                  </Tooltip>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="space-y-2 border-t border-border/50 pt-3">
          <div className="space-y-1">
            <Label htmlFor="tpl-name" className="text-xs">
              {t("broadcast.template_name")}
            </Label>
            <Input
              id="tpl-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              className="h-8"
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="tpl-body" className="text-xs">
              {t("broadcast.template_body")}
            </Label>
            <Textarea
              id="tpl-body"
              value={body}
              onChange={(event) => setBody(event.target.value)}
              rows={4}
            />
          </div>
          <Button
            size="sm"
            disabled={!name || !body || create.isPending}
            onClick={() => create.mutate()}
          >
            {create.isPending ? t("general.loading") : t("general.save")}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

interface ReminderRuleRow {
  id: number
  service_id: number
  service_name: string
  days_after: number
  message_template_id?: number | null
  template_name?: string | null
  is_active: boolean
}

/** Master pengingat: layanan X diingatkan setelah N hari. */
export function RemindersDialog({ tenant, open, onOpenChange }: ToolDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [serviceId, setServiceId] = useState("")
  const [days, setDays] = useState("30")
  const [templateId, setTemplateId] = useState("")

  useEffect(() => {
    if (!open) {
      setServiceId("")
      setDays("30")
      setTemplateId("")
    }
  }, [open])

  const rules = useQuery({
    queryKey: ["reminder-rules", tenant],
    queryFn: () =>
      apiGet<{ data: ReminderRuleRow[] }>(`/${tenant}/clinic/reminder-rules`),
    enabled: open,
  })

  const services = useQuery({
    queryKey: ["services", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string }[] }>(
        `/${tenant}/clinic/services`,
        { per_page: 200 },
      ),
    enabled: open,
  })

  const templates = useQuery({
    queryKey: ["message-templates", tenant],
    queryFn: () =>
      apiGet<{ data: Template[] }>(`/${tenant}/clinic/message-templates`),
    enabled: open,
  })

  const invalidate = () => qc.invalidateQueries({ queryKey: ["reminder-rules"] })

  const create = useMutation({
    mutationFn: () =>
      apiPost(`/${tenant}/clinic/reminder-rules`, {
        service_id: Number(serviceId),
        days_after: Number(days),
        message_template_id: templateId ? Number(templateId) : null,
      }),
    onSuccess: () => {
      toast.success(t("broadcast.reminder_saved"))
      setServiceId("")
      invalidate()
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const toggle = useMutation({
    mutationFn: (rule: ReminderRuleRow) =>
      apiPut(`/${tenant}/clinic/reminder-rules/${rule.id}`, {
        days_after: rule.days_after,
        message_template_id: rule.message_template_id,
        is_active: !rule.is_active,
      }),
    onSuccess: invalidate,
    onError: (err: ApiError) => toast.error(err.message),
  })

  const remove = useMutation({
    mutationFn: (id: number) =>
      apiDelete(`/${tenant}/clinic/reminder-rules/${id}`),
    onSuccess: () => {
      toast.success(t("broadcast.reminder_deleted"))
      invalidate()
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t("broadcast.reminders")}</DialogTitle>
          <DialogDescription>{t("broadcast.reminder_hint")}</DialogDescription>
        </DialogHeader>

        {rules.isLoading ? (
          <Skeleton className="h-16 w-full" />
        ) : (rules.data?.data.length ?? 0) === 0 ? (
          <p className="py-2 text-center text-xs text-muted-foreground">
            {t("broadcast.no_rules")}
          </p>
        ) : (
          <ul className="divide-y divide-border/50">
            {rules.data?.data.map((rule) => (
              <li key={rule.id} className="flex items-center gap-3 py-2">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{rule.service_name}</p>
                  <p className="text-xs text-muted-foreground tabular-nums">
                    {t("broadcast.reminder_days")}: {rule.days_after}
                    {rule.template_name ? ` · ${rule.template_name}` : ""}
                  </p>
                </div>
                <Switch
                  checked={rule.is_active}
                  onCheckedChange={() => toggle.mutate(rule)}
                  aria-label={rule.service_name}
                />
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                      aria-label={t("general.delete")}
                      onClick={() => remove.mutate(rule.id)}
                    >
                      <HugeiconsIcon
                        icon={Delete02Icon}
                        strokeWidth={2}
                        className="size-3.5"
                      />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{t("general.delete")}</TooltipContent>
                </Tooltip>
              </li>
            ))}
          </ul>
        )}

        <div className="grid gap-2 border-t border-border/50 pt-3 sm:grid-cols-[1fr_7rem_1fr_auto]">
          <NativeSelect
            value={serviceId}
            onChange={(event) => setServiceId(event.target.value)}
            aria-label={t("service.title")}
          >
            <NativeSelectOption value="">{t("service.title")}</NativeSelectOption>
            {(services.data?.data ?? []).map((service) => (
              <NativeSelectOption key={service.id} value={String(service.id)}>
                {service.name}
              </NativeSelectOption>
            ))}
          </NativeSelect>
          <Input
            type="number"
            min={1}
            max={730}
            value={days}
            onChange={(event) => setDays(event.target.value)}
            aria-label={t("broadcast.reminder_days")}
            className="tabular-nums"
          />
          <NativeSelect
            value={templateId}
            onChange={(event) => setTemplateId(event.target.value)}
            aria-label={t("broadcast.templates")}
          >
            <NativeSelectOption value="">
              {t("broadcast.templates")} —
            </NativeSelectOption>
            {(templates.data?.data ?? []).map((template) => (
              <NativeSelectOption key={template.id} value={String(template.id)}>
                {template.name}
              </NativeSelectOption>
            ))}
          </NativeSelect>
          <Button
            size="sm"
            disabled={!serviceId || !Number(days) || create.isPending}
            onClick={() => create.mutate()}
          >
            {t("general.save")}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

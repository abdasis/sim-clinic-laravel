import { useEffect, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  Delete02Icon,
  Logout01Icon,
  RefreshIcon,
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
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
import { formatDateTime } from "#/lib/format.ts"

interface ToolDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export interface ConnectionState {
  available: boolean
  provisioned: boolean
  connected: boolean
  qr: string | null
  number: string | null
  name: string | null
  error: boolean
}

interface PairingCode {
  pairing_code: string | null
  expires_at: string | null
}

/**
 * Label yang belum ada di kamus backend. Kuncinya tetap dipakai lebih dulu,
 * jadi begitu pengelola menambahkan terjemahannya, fallback ini berhenti
 * terpakai tanpa mengubah komponen.
 */
const LOCAL_LABELS: Record<string, string> = {
  "broadcast.pairing_phone": "Nomor WhatsApp",
  "broadcast.pairing_request": "Minta kode pairing",
  "broadcast.pairing_code": "Kode pairing",
  "broadcast.pairing_expires": "Kode berlaku sampai :time",
  "broadcast.disconnect": "Putuskan",
  "broadcast.disconnect_hint": "Putuskan sambungan sekarang. Sesi gateway tetap ada dan bisa disambungkan lagi.",
  "broadcast.restart": "Mulai ulang",
  "broadcast.restart_hint": "Mulai ulang sesi gateway. Tunggu beberapa saat sampai QR terbit kembali.",
  "broadcast.delete_session": "Hapus sesi",
  "broadcast.delete_session_hint": "Hapus sesi di gateway. Nomor WhatsApp harus dipindai ulang setelahnya.",
  "broadcast.delete_session_confirm":
    "Sesi WhatsApp klinik di gateway akan dihapus dan nomornya perlu dipindai ulang. Lanjutkan?",
  "broadcast.session_deleted": "Sesi WhatsApp dihapus.",
  "broadcast.session_disconnected": "Sambungan WhatsApp diputus.",
  "broadcast.session_restarted": "Sesi WhatsApp dimulai ulang.",
}

function localLabel(t: (key: string) => string, key: string, values?: Record<string, string>) {
  const raw = t(key)
  let value = raw === key ? (LOCAL_LABELS[key] ?? key) : raw

  for (const [name, replacement] of Object.entries(values ?? {})) {
    value = value.replace(`:${name}`, replacement)
  }

  return value
}

/**
 * Koneksi WhatsApp lewat gateway: status terhubung, QR untuk dipindai, atau
 * kode pairing bila nomornya disambungkan tanpa kamera. Selagi belum
 * terhubung, status ditanya ulang tiap 4 detik supaya begitu admin memindai,
 * layarnya langsung berubah tanpa muat ulang.
 *
 * Menanya dan menyalakan sengaja dipisah. Tanyaan tiap 4 detik yang ikut
 * menyalakan sesi berarti memulai ulang sambungannya belasan kali semenit,
 * dan nomornya tidak pernah sempat tersambung — persis keluhan "WA-nya
 * keluar terus" dari klinik. Menyalakan dilakukan sekali saat dialognya
 * dibuka, dan sesudah itu hanya kalau admin memintanya.
 */
export function ConnectionDialog({ tenant, open, onOpenChange }: ToolDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [phone, setPhone] = useState("")
  const [pairing, setPairing] = useState<PairingCode | null>(null)
  const [confirmDelete, setConfirmDelete] = useState(false)

  const connection = useQuery({
    queryKey: ["wa-connection", tenant],
    queryFn: () =>
      apiGet<{ data: ConnectionState }>(`/${tenant}/clinic/broadcasts/connection`),
    enabled: open,
    refetchInterval: (query) =>
      open && query.state.data && !query.state.data.data.connected ? 4000 : false,
  })

  const prepare = useMutation({
    mutationFn: () =>
      apiPost<{ data: ConnectionState }>(
        `/${tenant}/clinic/broadcasts/connection/prepare`,
        {},
      ),
    onSuccess: (result) => {
      qc.setQueryData(["wa-connection", tenant], result)
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const pairingMutation = useMutation({
    mutationFn: () =>
      apiPost<{ data: PairingCode }>(
        `/${tenant}/clinic/broadcasts/connection/pairing`,
        { phone },
      ),
    onSuccess: (result) => setPairing(result.data),
    onError: (err: ApiError) => toast.error(err.message),
  })

  // Tindakan sesi mengembalikan keadaan terbaru, jadi cache langsung ditimpa —
  // tanpa menunggu tanyaan berikutnya.
  const restart = useMutation({
    mutationFn: () =>
      apiPost<{ data: ConnectionState }>(
        `/${tenant}/clinic/broadcasts/connection/restart`,
        {},
      ),
    onSuccess: (result) => {
      qc.setQueryData(["wa-connection", tenant], result)
      toast.success(localLabel(t, "broadcast.session_restarted"))
    },
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
      toast.success(localLabel(t, "broadcast.session_disconnected"))
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const removeSession = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/broadcasts/connection`),
    onSuccess: () => {
      toast.success(localLabel(t, "broadcast.session_deleted"))
      setConfirmDelete(false)
      qc.invalidateQueries({ queryKey: ["wa-connection", tenant] })
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setConfirmDelete(false)
    },
  })

  // Sekali tiap dialog dibuka, bukan tiap tanyaan: sesi yang sedang membuka
  // WhatsApp Web butuh puluhan detik dan tidak boleh dipotong di tengah.
  useEffect(() => {
    if (open) {
      prepare.mutate()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, tenant])

  // Kode pairing hanya berlaku untuk satu percobaan; menutup dialog berarti
  // percobaannya selesai.
  useEffect(() => {
    if (open) return
    setPhone("")
    setPairing(null)
    setConfirmDelete(false)
  }, [open])

  const state = connection.data?.data

  // Sesi yang belum pernah dibuat di gateway tidak punya apa pun untuk
  // dihapus; tombolnya baru masuk akal setelah `provisioned`.
  const deleteSessionButton = state?.provisioned ? (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant="outline"
          size="icon"
          aria-label={localLabel(t, "broadcast.delete_session")}
          className="text-muted-foreground hover:text-destructive"
          onClick={() => setConfirmDelete(true)}
          disabled={removeSession.isPending}
        >
          <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} className="size-4" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>{localLabel(t, "broadcast.delete_session_hint")}</TooltipContent>
    </Tooltip>
  ) : null

  return (
    <>
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
          // Gateway belum siap untuk klinik ini: alamat servernya belum diisi
          // pengelola, atau sesinya belum disiapkan.
          <p className="rounded-md border border-dashed border-border/60 px-4 py-6 text-center text-sm text-pretty text-muted-foreground">
            {t("broadcast.gateway_not_ready")}
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
            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-primary/20 pt-3">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="sm"
                    className="gap-1.5 text-muted-foreground hover:text-destructive"
                    onClick={() => disconnect.mutate()}
                    disabled={disconnect.isPending}
                  >
                    <HugeiconsIcon
                      icon={Logout01Icon}
                      strokeWidth={2}
                      className="size-4"
                    />
                    {disconnect.isPending
                      ? t("general.loading")
                      : localLabel(t, "broadcast.disconnect")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  {localLabel(t, "broadcast.disconnect_hint")}
                </TooltipContent>
              </Tooltip>
              {deleteSessionButton}
            </div>
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
              {state.qr ? t("broadcast.scan_hint") : t("broadcast.session_needs_start")}
            </p>

            {pairing ? (
              <div className="space-y-1 rounded-md border border-border/50 bg-muted/30 px-3 py-2.5 text-left">
                <p className="text-xs text-muted-foreground">
                  {localLabel(t, "broadcast.pairing_code")}
                </p>
                <p className="font-mono text-lg font-semibold tracking-[0.2em] tabular-nums">
                  {pairing.pairing_code ?? "—"}
                </p>
                {pairing.expires_at ? (
                  <p className="text-xxs text-muted-foreground">
                    {localLabel(t, "broadcast.pairing_expires", {
                      time: formatDateTime(pairing.expires_at),
                    })}
                  </p>
                ) : null}
              </div>
            ) : null}

            {/* Menyambung tanpa kamera: masukkan nomor, lalu ketik kodenya di
                WhatsApp. Formulirnya tetap sederhana — satu nomor, satu aksi. */}
            <form
              className="space-y-2 text-left"
              onSubmit={(event) => {
                event.preventDefault()
                if (phone.trim() === "" || pairingMutation.isPending) return
                pairingMutation.mutate()
              }}
            >
              <div className="space-y-1">
                <Label htmlFor="wa-pairing-phone" className="text-xs">
                  {localLabel(t, "broadcast.pairing_phone")}
                </Label>
                <Input
                  id="wa-pairing-phone"
                  type="tel"
                  inputMode="tel"
                  autoComplete="tel"
                  placeholder="08xx"
                  value={phone}
                  onChange={(event) => setPhone(event.target.value)}
                  className="tabular-nums"
                />
              </div>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    className="w-full"
                    disabled={phone.trim() === "" || pairingMutation.isPending}
                  >
                    {pairingMutation.isPending
                      ? t("general.loading")
                      : localLabel(t, "broadcast.pairing_request")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  {localLabel(t, "broadcast.pairing_request")}
                  <Kbd>Enter</Kbd>
                </TooltipContent>
              </Tooltip>
            </form>

            {/* Menyalakan ulang atas permintaan, bukan otomatis tiap tanyaan.
                Ditahan 30 detik di sisi server, jadi menekannya berkali-kali
                tidak bisa memotong sesi yang sedang bangun. */}
            <div className="flex flex-wrap items-center justify-center gap-2">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => prepare.mutate()}
                    disabled={prepare.isPending}
                  >
                    {prepare.isPending
                      ? t("general.loading")
                      : t("broadcast.reconnect")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t("broadcast.reconnect_hint")}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="outline"
                    size="sm"
                    className="gap-1.5"
                    onClick={() => restart.mutate()}
                    disabled={restart.isPending}
                  >
                    <HugeiconsIcon
                      icon={RefreshIcon}
                      strokeWidth={2}
                      className="size-4"
                    />
                    {restart.isPending
                      ? t("general.loading")
                      : localLabel(t, "broadcast.restart")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{localLabel(t, "broadcast.restart_hint")}</TooltipContent>
              </Tooltip>
              {deleteSessionButton}
            </div>
            {state.error ? (
              <p className="text-xs text-destructive">{t("broadcast.gateway_error")}</p>
            ) : null}
          </div>
        )}
      </DialogContent>
      </Dialog>

      <AlertDialog open={confirmDelete} onOpenChange={setConfirmDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {localLabel(t, "broadcast.delete_session")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {localLabel(t, "broadcast.delete_session_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={removeSession.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={removeSession.isPending}
              onClick={(event) => {
                event.preventDefault()
                removeSession.mutate()
              }}
            >
              {removeSession.isPending ? t("general.loading") : t("general.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
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

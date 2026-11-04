import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  CancelCircleIcon,
  CheckmarkCircle02Icon,
} from "@hugeicons/core-free-icons"
import { z } from "zod"
import { toast } from "sonner"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { WebhookUrl } from "#/components/webhook-url.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { BroadcastSettings } from "./whatsapp-types.ts"

const DAILY_LIMIT_MIN = 1
const DAILY_LIMIT_MAX = 10_000

function buildSchema(
  requiredMessage: string,
  invalidMessage: string,
  rangeMessage: string,
) {
  return z.object({
    base_url: z.string().trim().min(1, requiredMessage).url(invalidMessage),
    // Kosong berarti kunci lama dipertahankan; backend yang memutuskan.
    api_key: z.string().trim().max(255).optional(),
    // Disimpan sebagai teks karena input number di form mengembalikan string.
    // Kosong berarti memakai batas bawaan platform.
    daily_message_limit: z
      .string()
      .trim()
      .refine((value) => value === "" || /^\d+$/.test(value), rangeMessage)
      .refine((value) => {
        if (value === "") return true
        const limit = Number(value)
        return limit >= DAILY_LIMIT_MIN && limit <= DAILY_LIMIT_MAX
      }, rangeMessage),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

interface SettingsResponse {
  data: BroadcastSettings
  meta?: { message?: string }
}

/**
 * Kredensial gateway milik klinik. Kunci API tidak pernah dikirim balik oleh
 * server, jadi kolomnya selalu mulai kosong dan hanya dikirim bila diisi.
 */
export function CredentialsCard({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const settings = useQuery({
    queryKey: ["broadcast-settings", tenant],
    queryFn: () =>
      apiGet<{ data: BroadcastSettings }>(`/${tenant}/clinic/broadcasts/settings`),
  })

  const form = useForm(
    buildSchema(
      t("settings.whatsapp.base_url_required"),
      t("settings.whatsapp.base_url_invalid"),
      t("settings.whatsapp.daily_limit_range"),
    ),
    { defaultValues: { base_url: "", api_key: "", daily_message_limit: "" } },
  )

  const current = settings.data?.data

  useEffect(() => {
    if (!current) return

    form.reset({
      base_url: current.gateway_base_url ?? "",
      api_key: "",
      daily_message_limit:
        current.daily_message_limit == null
          ? ""
          : String(current.daily_message_limit),
    })
  }, [current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      const apiKey = values.api_key?.trim() ?? ""
      const limit = values.daily_message_limit.trim()
      const payload: {
        base_url: string
        api_key?: string
        daily_message_limit: number | null
      } = {
        base_url: values.base_url.trim(),
        // `null` menghapus override supaya klinik kembali ke batas bawaan.
        daily_message_limit: limit === "" ? null : Number(limit),
      }
      if (apiKey !== "") payload.api_key = apiKey

      return apiPut<SettingsResponse>(
        `/${tenant}/clinic/broadcasts/settings`,
        payload,
      )
    },
    onSuccess: (result) => {
      toast.success(
        result?.meta?.message ?? t("settings.whatsapp.credentials_saved"),
      )
      form.setValue("api_key", "")
      void qc.invalidateQueries({ queryKey: ["broadcast-settings"] })
      void qc.invalidateQueries({ queryKey: ["wa-connection"] })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">
          {t("settings.whatsapp.credentials_title")}
        </CardTitle>
        <CardDescription className="text-pretty">
          {t("settings.whatsapp.credentials_desc")}
        </CardDescription>
      </CardHeader>

      <CardContent>
        {settings.isLoading ? (
          <Skeleton className="h-32 w-full" />
        ) : (
          <div className="space-y-5">
            <Form {...form}>
              <form
                onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
                className="space-y-4"
              >
                <FormInput
                  control={form.control}
                  name="base_url"
                  type="url"
                  autoComplete="url"
                  label={t("settings.whatsapp.base_url_label")}
                  placeholder="https://gateway.klinik.test"
                  description={t("settings.whatsapp.base_url_hint")}
                  required
                  inputClassName="font-mono"
                />
                <FormInput
                  control={form.control}
                  name="api_key"
                  type="password"
                  autoComplete="new-password"
                  label={t("settings.whatsapp.api_key_label")}
                  placeholder={t("settings.whatsapp.api_key_placeholder")}
                  description={t("settings.whatsapp.api_key_hint")}
                  inputClassName="font-mono"
                />
                <FormInput
                  control={form.control}
                  name="daily_message_limit"
                  type="number"
                  min={DAILY_LIMIT_MIN}
                  max={DAILY_LIMIT_MAX}
                  step={1}
                  inputMode="numeric"
                  label={t("settings.whatsapp.daily_limit_label")}
                  placeholder={t("settings.whatsapp.daily_limit_placeholder")}
                  description={t("settings.whatsapp.daily_limit_hint")}
                  inputClassName="tabular-nums"
                />

                <FormSubmit loading={mutation.isPending}>
                  {t("settings.whatsapp.save_credentials")}
                </FormSubmit>
              </form>
            </Form>

            <div className="space-y-2">
              <p className="text-xs font-medium text-muted-foreground">
                {t("settings.whatsapp.current_status")}
              </p>

              <dl className="divide-y divide-border/50 rounded-lg border border-border/60 text-sm">
                <StatusRow
                  label={t("settings.whatsapp.gateway")}
                  ok={current?.gateway_available === true}
                  okLabel={t("settings.whatsapp.configured")}
                  missingLabel={t("settings.whatsapp.not_configured")}
                />
                <StatusRow
                  label={t("settings.whatsapp.api_key_label")}
                  ok={current?.api_key_configured === true}
                  okLabel={t("settings.whatsapp.api_key_set")}
                  missingLabel={t("settings.whatsapp.api_key_missing")}
                />
                <StatusRow
                  label={t("settings.whatsapp.webhook")}
                  ok={current?.webhook_configured === true}
                  okLabel={t("settings.whatsapp.webhook_registered")}
                  missingLabel={t("settings.whatsapp.webhook_missing")}
                />
                <div className="space-y-1.5 px-3 py-2">
                  <dt className="text-muted-foreground">
                    {t("settings.whatsapp.webhook_url_label")}
                  </dt>
                  <dd>
                    <WebhookUrl
                      url={current?.webhook_url}
                      copyLabel={t("settings.whatsapp.copy_webhook_url")}
                      copiedLabel={t("settings.whatsapp.copied")}
                      emptyLabel={t("settings.whatsapp.webhook_url_empty")}
                    />
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3 px-3 py-2">
                  <dt className="text-muted-foreground">
                    {t("settings.whatsapp.base_url_label")}
                  </dt>
                  <dd
                    className="truncate font-mono text-xs"
                    title={current?.gateway_base_url ?? undefined}
                  >
                    {current?.gateway_base_url || "—"}
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3 px-3 py-2">
                  <dt className="text-muted-foreground">
                    {t("settings.whatsapp.session")}
                  </dt>
                  <dd className="truncate font-mono text-xs">
                    {current?.session || t("settings.whatsapp.session_empty")}
                  </dd>
                </div>
                <div className="flex items-center justify-between gap-3 px-3 py-2">
                  <dt className="text-muted-foreground">
                    {t("settings.whatsapp.daily_limit")}
                  </dt>
                  <dd className="tabular-nums">
                    {current?.daily_message_limit == null
                      ? t("settings.whatsapp.daily_limit_default_current")
                      : current.daily_message_limit.toLocaleString("id-ID")}
                  </dd>
                </div>
              </dl>

              <p className="text-xs text-pretty text-muted-foreground">
                {t("settings.whatsapp.managed_hint")}
              </p>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function StatusRow({
  label,
  ok,
  okLabel,
  missingLabel,
}: {
  label: string
  ok: boolean
  okLabel: string
  missingLabel: string
}) {
  return (
    <div className="flex items-center justify-between gap-3 px-3 py-2">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="flex items-center gap-1.5">
        <HugeiconsIcon
          icon={ok ? CheckmarkCircle02Icon : CancelCircleIcon}
          strokeWidth={2}
          className={ok ? "size-4 text-primary" : "size-4 text-muted-foreground"}
          aria-hidden="true"
        />
        <span className={ok ? "text-foreground" : "text-muted-foreground"}>
          {ok ? okLabel : missingLabel}
        </span>
      </dd>
    </div>
  )
}

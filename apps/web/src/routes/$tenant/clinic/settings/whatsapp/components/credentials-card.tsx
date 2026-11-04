import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  CancelCircleIcon,
  CheckmarkCircle02Icon,
} from "@hugeicons/core-free-icons"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Form } from "#/components/ui/form.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { BroadcastSettings, ConnectionState } from "./whatsapp-types.ts"

function buildSchema(message: string) {
  return z.object({
    // Boleh kosong: klinik yang belum punya sesi tetap bisa kirim manual.
    session: z
      .string()
      .max(64)
      .regex(/^([a-z0-9-]{3,64})?$/, message),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

/**
 * Kredensial gateway hanya ditampilkan sebagai status terisi atau belum.
 * Nilai rahasianya tidak pernah dikirim ke peramban, dan tidak ada kolom
 * untuk mengetiknya di sini — alamat serta kuncinya milik pengelola platform.
 */
export function CredentialsCard({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const settings = useQuery({
    queryKey: ["broadcast-settings", tenant],
    queryFn: () =>
      apiGet<{ data: BroadcastSettings }>(`/${tenant}/clinic/broadcasts/settings`),
  })

  const connection = useQuery({
    queryKey: ["wa-connection", tenant],
    queryFn: () =>
      apiGet<{ data: ConnectionState }>(`/${tenant}/clinic/broadcasts/connection`),
  })

  const form = useForm(buildSchema(t("broadcast.session_format")), {
    defaultValues: { session: "" },
  })

  const current = settings.data?.data
  const provisioned = connection.data?.data.provisioned === true

  useEffect(() => {
    if (!current) return
    form.reset({ session: current.session ?? "" })
  }, [current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/broadcasts/settings`, {
        session: values.session.trim() || null,
      }),
    onSuccess: () => {
      toast.success(t("broadcast.settings_saved"))
      void qc.invalidateQueries({ queryKey: ["broadcast-settings", tenant] })
      void qc.invalidateQueries({ queryKey: ["wa-connection", tenant] })
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
          <div className="space-y-4">
            <dl className="divide-y divide-border/50 rounded-lg border border-border/60 text-sm">
              <StatusRow
                label={t("settings.whatsapp.gateway")}
                ok={current?.gateway_available === true}
                okLabel={t("settings.whatsapp.configured")}
                missingLabel={t("settings.whatsapp.not_configured")}
              />
              <StatusRow
                label={t("settings.whatsapp.api_key")}
                ok={current?.api_key_configured === true}
                okLabel={t("settings.whatsapp.configured")}
                missingLabel={t("settings.whatsapp.not_configured")}
              />
              <StatusRow
                label={t("settings.whatsapp.webhook")}
                ok={current?.webhook_configured === true}
                okLabel={t("settings.whatsapp.configured")}
                missingLabel={t("settings.whatsapp.not_configured")}
              />
              <div className="flex items-center justify-between gap-3 px-3 py-2">
                <dt className="text-muted-foreground">
                  {t("settings.whatsapp.base_url")}
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
                  {t("settings.whatsapp.daily_limit")}
                </dt>
                <dd className="tabular-nums">
                  {current?.daily_message_limit == null
                    ? t("settings.whatsapp.unlimited")
                    : current.daily_message_limit.toLocaleString("id-ID")}
                </dd>
              </div>
            </dl>

            <p className="text-xs text-pretty text-muted-foreground">
              {t("settings.whatsapp.managed_hint")}
            </p>

            <Form {...form}>
              <form
                onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
                className="space-y-3 border-t border-border/50 pt-4"
              >
                <FormInput
                  control={form.control}
                  name="session"
                  label={t("broadcast.session_label")}
                  placeholder="klinik-satu"
                  description={
                    provisioned
                      ? t("broadcast.session_locked")
                      : t("broadcast.session_empty")
                  }
                  disabled={provisioned}
                  inputClassName="font-mono"
                />
                <div className="flex justify-end">
                  <FormSubmit loading={mutation.isPending} disabled={provisioned}>
                    {t("general.save")}
                  </FormSubmit>
                </div>
              </form>
            </Form>
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

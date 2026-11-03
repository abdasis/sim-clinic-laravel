import { useEffect, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import {
  TemplatePicker,
  isTemplateComplete,
} from "./template-picker.tsx"
import type { BroadcastTemplate } from "./template-picker.tsx"
import type { ApiError } from "#/lib/api.ts"

/**
 * Pesannya diterjemahkan saat schema dibuat, bukan saat dirender: zod
 * menyimpan string jadi, bukan kunci.
 */
function buildSchema(
  sessionFormat: string,
  phoneFormat: string,
  wabaFormat: string,
) {
  return z.object({
    // Boleh kosong: klinik yang belum pakai WAHA tetap bisa kirim manual.
    session: z.string().max(100).regex(/^[a-z0-9-]*$/, sessionFormat).optional(),
    // Angka saja dan boleh kosong: nomor Cloud API hanya dipakai klinik yang
    // membalas lewat jalur Meta.
    phone_number_id: z.string().max(32).regex(/^\d*$/, phoneFormat).optional(),
    waba_id: z.string().max(32).regex(/^\d*$/, wabaFormat).optional(),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

interface Settings {
  session?: string | null
  phone_number_id?: string | null
  waba_id?: string | null
  reminder_template?: BroadcastTemplate | null
  waha_available: boolean
}

interface BroadcastSettingsDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Yang diatur klinik: sesi WAHA, nomor Cloud API, dan template pengingat.
 * Alamat server dan API key milik pengelola platform, diisi di halaman central.
 */
export function BroadcastSettingsDialog({
  tenant,
  open,
  onOpenChange,
}: BroadcastSettingsDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const settings = useQuery({
    queryKey: ["broadcast-settings", tenant],
    queryFn: () =>
      apiGet<{ data: Settings }>(`/${tenant}/clinic/broadcasts/settings`),
    enabled: open,
  })

  // Dibangun ulang tiap render: `t` baru bisa menjawab setelah terjemahan
  // termuat, dan react-hook-form membaca resolver terbaru saat memvalidasi.
  const form = useForm(
    buildSchema(
      t("broadcast.session_format"),
      t("broadcast.phone_number_id_format"),
      t("broadcast.waba_id_format"),
    ),
    { defaultValues: { session: "", phone_number_id: "", waba_id: "" } },
  )

  const [reminderTemplate, setReminderTemplate] =
    useState<BroadcastTemplate | null>(null)
  const current = settings.data?.data

  useEffect(() => {
    if (!open || !current) return

    form.reset({
      session: current.session ?? "",
      phone_number_id: current.phone_number_id ?? "",
      waba_id: current.waba_id ?? "",
    })
    setReminderTemplate(
      current.reminder_template
        ? { ...current.reminder_template, params: current.reminder_template.params ?? [] }
        : null,
    )
  }, [open, current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/broadcasts/settings`, {
        session: values.session?.trim() || null,
        phone_number_id: values.phone_number_id?.trim() || null,
        waba_id: values.waba_id?.trim() || null,
        reminder_template: reminderTemplate
          ? {
              name: reminderTemplate.name,
              language: reminderTemplate.language,
              body: reminderTemplate.body,
              header_type: reminderTemplate.header_type,
              params: reminderTemplate.params,
            }
          : null,
      }),
    onSuccess: () => {
      toast.success(t("broadcast.settings_saved"))
      qc.invalidateQueries({ queryKey: ["broadcast-settings"] })
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      qc.invalidateQueries({ queryKey: ["wa-connection"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const reminderReady =
    reminderTemplate === null || isTemplateComplete(reminderTemplate)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent layout="form">
        <DialogHeader>
          <DialogTitle>{t("broadcast.settings")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("broadcast.session_hint")}
          </DialogDescription>
        </DialogHeader>

        {settings.isLoading ? (
          <Skeleton className="h-16 w-full" />
        ) : (
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit((values) => {
                if (!reminderReady) return
                mutation.mutate(values)
              })}
              className="flex min-h-0 flex-1 flex-col gap-4"
            >
              <DialogBody className="space-y-4">
                {current && !current.waha_available ? (
                  <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
                    {t("broadcast.waha_not_configured")}
                  </p>
                ) : null}

                <FormInput
                  control={form.control}
                  name="session"
                  label={t("broadcast.session_label")}
                  placeholder="klinik-satu"
                  description={t("broadcast.session_empty")}
                  inputClassName="font-mono"
                />

                <FormInput
                  control={form.control}
                  name="phone_number_id"
                  label={t("broadcast.phone_number_id_label")}
                  description={t("broadcast.phone_number_id_hint")}
                  placeholder="109900000000000"
                  inputClassName="font-mono tabular-nums"
                />

                <FormInput
                  control={form.control}
                  name="waba_id"
                  label={t("broadcast.waba_id_label")}
                  description={t("broadcast.waba_id_hint")}
                  placeholder="109900000000000"
                  inputClassName="font-mono tabular-nums"
                />

                <TemplatePicker
                  tenant={tenant}
                  value={reminderTemplate}
                  onChange={setReminderTemplate}
                  enabled={open}
                  idPrefix="reminder-template"
                  label={t("broadcast.reminder_template_label")}
                  hint={t("broadcast.reminder_template_hint")}
                  showImageHint
                />
              </DialogBody>


              <DialogFooter>
                <FormSubmit loading={mutation.isPending} disabled={!reminderReady}>
                  {t("general.save")}
                </FormSubmit>
              </DialogFooter>
            </form>
          </Form>
        )}
      </DialogContent>
    </Dialog>
  )
}

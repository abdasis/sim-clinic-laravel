import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSwitch } from "#/components/forms/form-switch.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  offsetLabel,
  reminderSettingKey,
  useReminderSetting,
} from "./use-reminder-setting.ts"

/** Pilihan cepat yang benar-benar dipakai klinik, supaya tidak mengetik angka. */
const PRESETS = [30, 60, 120, 180, 1440]

const schema = z.object({
  is_active: z.boolean(),
  offset_minutes: z.coerce.number().int().min(1).max(10080),
  message_template_id: z.string(),
})

type Values = z.infer<typeof schema>

interface TemplateRow {
  id: number
  name: string
}

interface ReminderSettingsDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ReminderSettingsDialog({
  tenant,
  open,
  onOpenChange,
}: ReminderSettingsDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const setting = useReminderSetting(tenant, open)

  const templates = useQuery({
    queryKey: ["message-templates", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: TemplateRow[] }>(`/${tenant}/clinic/message-templates`, {
        per_page: 100,
      }),
    enabled: open,
  })

  const form = useForm(schema, {
    defaultValues: { is_active: false, offset_minutes: 60, message_template_id: "" },
  })

  const current = setting.data?.data

  useEffect(() => {
    if (!open || !current) return

    form.reset({
      is_active: current.is_active,
      offset_minutes: current.offset_minutes,
      message_template_id:
        current.message_template_id !== null
          ? String(current.message_template_id)
          : "",
    })
  }, [open, current, form])

  const isActive = form.watch("is_active")
  const offset = Number(form.watch("offset_minutes"))

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/bookings/reminder-settings`, {
        is_active: values.is_active,
        offset_minutes: values.offset_minutes,
        message_template_id: values.message_template_id
          ? Number(values.message_template_id)
          : null,
      }),
    onSuccess: () => {
      toast.success(t("booking.reminder_saved"))
      qc.invalidateQueries({ queryKey: reminderSettingKey(tenant) })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("booking.reminder_settings")}</DialogTitle>
          <DialogDescription>
            {t("booking.reminder_settings_desc")}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormSwitch
              control={form.control}
              name="is_active"
              label={t("booking.reminder_active")}
              description={t("booking.reminder_active_hint")}
            />

            <div className="space-y-2">
              <FormInput
                control={form.control}
                name="offset_minutes"
                type="number"
                min={1}
                max={10080}
                label={t("booking.reminder_offset")}
                description={t("booking.reminder_offset_hint")}
                disabled={!isActive}
                inputClassName="w-28 tabular-nums"
              />
              <div className="flex flex-wrap gap-1.5">
                {PRESETS.map((minutes) => {
                  const selected = offset === minutes

                  return (
                    <button
                      key={minutes}
                      type="button"
                      disabled={!isActive}
                      onClick={() =>
                        form.setValue("offset_minutes", minutes, {
                          shouldDirty: true,
                        })
                      }
                      className={[
                        "rounded-md border px-2 py-1 text-xs transition-colors",
                        "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                        "disabled:pointer-events-none disabled:opacity-50",
                        selected
                          ? "border-border bg-muted font-medium text-foreground"
                          : "border-border/50 text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                      ].join(" ")}
                    >
                      {offsetLabel(minutes).replace(" sebelum jadwal", "")}
                    </button>
                  )
                })}
              </div>
              {isActive && Number.isFinite(offset) && offset > 0 ? (
                <p className="text-xs text-muted-foreground">
                  {t("booking.reminder_preview")} {offsetLabel(offset)}.
                </p>
              ) : null}
            </div>

            <FormSelect
              control={form.control}
              name="message_template_id"
              label={t("booking.reminder_template")}
              placeholder={t("booking.reminder_template_default")}
              description={t("booking.reminder_template_hint")}
              disabled={!isActive}
              options={(templates.data?.data ?? []).map((row) => ({
                label: row.name,
                value: String(row.id),
              }))}
            />

            <DialogFooter>
              <FormSubmit loading={mutation.isPending}>
                {t("general.save")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

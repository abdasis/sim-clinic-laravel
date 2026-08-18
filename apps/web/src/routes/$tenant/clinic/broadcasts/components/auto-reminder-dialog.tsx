import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"

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
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormSwitch } from "#/components/forms/form-switch.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  days: z.coerce.number().int().gte(1).lte(730),
  message_template_id: z.string().optional(),
  is_active: z.boolean().default(false),
})

type Values = z.input<typeof schema>

interface Setting {
  days: number | null
  message_template_id: number | null
  template_name: string | null
  is_active: boolean
}

interface Template {
  id: number
  name: string
}

interface AutoReminderDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Follow-up otomatis: satu ambang hari untuk seluruh klinik, dihitung dari
 * rekam medis terakhir pasien. Yang dikirimi hanya pasien yang bersedia
 * menerima broadcast.
 */
export function AutoReminderDialog({
  tenant,
  open,
  onOpenChange,
}: AutoReminderDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const setting = useQuery({
    queryKey: ["auto-reminder", tenant],
    queryFn: () =>
      apiGet<{ data: Setting }>(`/${tenant}/clinic/broadcasts/auto-reminder`),
    enabled: open,
  })

  const templates = useQuery({
    queryKey: ["message-templates", tenant],
    queryFn: () =>
      apiGet<{ data: Template[] }>(`/${tenant}/clinic/message-templates`),
    enabled: open,
  })

  const form = useForm(schema, {
    defaultValues: { days: 30, message_template_id: "", is_active: false },
  })

  const current = setting.data?.data

  useEffect(() => {
    if (!open || !current) return

    form.reset({
      days: current.days ?? 30,
      message_template_id: current.message_template_id
        ? String(current.message_template_id)
        : "",
      is_active: current.is_active,
    })
  }, [open, current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/broadcasts/auto-reminder`, {
        days: Number(values.days),
        message_template_id: values.message_template_id
          ? Number(values.message_template_id)
          : null,
        is_active: Boolean(values.is_active),
      }),
    onSuccess: () => {
      toast.success(t("broadcast.auto_reminder_saved"))
      qc.invalidateQueries({ queryKey: ["auto-reminder"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const templateOptions = [
    { value: "", label: t("broadcast.auto_reminder_default_template") },
    ...(templates.data?.data ?? []).map((template) => ({
      value: String(template.id),
      label: template.name,
    })),
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("broadcast.auto_reminder_title")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("broadcast.auto_reminder_hint")}
          </DialogDescription>
        </DialogHeader>

        {setting.isLoading ? (
          <div className="space-y-3">
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-9 w-full" />
          </div>
        ) : (
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
              className="space-y-4"
            >
              <FormSwitch
                control={form.control}
                name="is_active"
                label={t("broadcast.auto_reminder_active")}
                description={t("broadcast.auto_reminder_days_hint")}
              />

              <FormInput
                control={form.control}
                name="days"
                type="number"
                min={1}
                max={730}
                label={t("broadcast.auto_reminder_days")}
                required
                inputClassName="tabular-nums"
              />

              <FormSelect
                control={form.control}
                name="message_template_id"
                label={t("broadcast.auto_reminder_template")}
                options={templateOptions}
              />

              <DialogFooter>
                <FormSubmit loading={mutation.isPending}>
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

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
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  driver: z.string().min(1),
  api_url: z.string().optional(),
  api_token: z.string().optional(),
})

type Values = z.infer<typeof schema>

interface BroadcastSettingsDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Pilih cara kirim: manual lewat tautan wa.me (tanpa setup, dari WhatsApp
 * klinik sendiri) atau gateway HTTP untuk blast otomatis dari server.
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
      apiGet<{
        data: {
          driver: string
          api_url?: string | null
          has_token: boolean
          sidecar_available?: boolean
        }
      }>(`/${tenant}/clinic/broadcasts/settings`),
    enabled: open,
  })

  const form = useForm(schema, {
    defaultValues: { driver: "manual", api_url: "", api_token: "" },
  })
  const driver = form.watch("driver")

  useEffect(() => {
    if (!open || !settings.data) return

    form.reset({
      driver: settings.data.data.driver,
      api_url: settings.data.data.api_url ?? "",
      // Token tidak pernah dikirim balik; field kosong berarti "jangan ubah".
      api_token: "",
    })
  }, [open, settings.data, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/broadcasts/settings`, values),
    onSuccess: () => {
      toast.success(t("broadcast.settings_saved"))
      qc.invalidateQueries({ queryKey: ["broadcast-settings"] })
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
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
          <DialogTitle>{t("broadcast.settings")}</DialogTitle>
          <DialogDescription>
            {t("broadcast.driver.manual")} · {t("broadcast.driver.gateway")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <FormSelect
              control={form.control}
              name="driver"
              label={t("broadcast.driver_label")}
              options={[
                { label: t("broadcast.driver.manual"), value: "manual" },
                { label: t("broadcast.driver.gateway"), value: "gateway" },
                { label: t("broadcast.driver.qr"), value: "qr" },
              ]}
            />

            {driver === "qr" && !settings.data?.data.sidecar_available ? (
              <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                {t("broadcast.sidecar_missing")}
              </p>
            ) : null}

            {driver === "gateway" ? (
              <>
                <FormInput
                  control={form.control}
                  name="api_url"
                  label={t("broadcast.api_url")}
                  placeholder="https://api.fonnte.com/send"
                  required
                />
                <FormInput
                  control={form.control}
                  name="api_token"
                  label={t("broadcast.api_token")}
                  type="password"
                  description={
                    settings.data?.data.has_token
                      ? t("broadcast.token_saved")
                      : undefined
                  }
                />
              </>
            ) : null}

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

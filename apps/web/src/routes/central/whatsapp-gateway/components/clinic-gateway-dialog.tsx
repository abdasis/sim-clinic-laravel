import { useEffect } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
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
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { ClinicGatewayPayload, ClinicGatewayRow } from "./gateway-types.ts"

function buildSchema(sessionFormat: string, dailyLimitFormat: string) {
  return z.object({
    // Kosong berarti kunci lama dipertahankan; backend yang memutuskan.
    api_key: z.string().max(255).optional(),
    session: z
      .string()
      .max(64)
      .regex(/^([a-z0-9-]{3,64})?$/, sessionFormat)
      .optional(),
    // Disimpan sebagai teks agar kolom kosong tidak berubah jadi 0.
    daily_message_limit: z
      .string()
      .regex(/^(\d+)?$/, dailyLimitFormat)
      .optional(),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

interface ClinicGatewayDialogProps {
  row: ClinicGatewayRow
  open: boolean
  onOpenChange: (open: boolean) => void
}

interface MutationResult {
  meta?: { message?: string }
}

/**
 * Kredensial satu klinik. Nilai rahasia tidak pernah dikirim balik oleh server,
 * jadi kolom kunci API selalu mulai kosong dan hanya dikirim bila diisi.
 */
export function ClinicGatewayDialog({
  row,
  open,
  onOpenChange,
}: ClinicGatewayDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const form = useForm(
    buildSchema(
      t("whatsapp_gateway.session_format"),
      t("whatsapp_gateway.daily_limit_format"),
    ),
    {
      defaultValues: { api_key: "", session: "", daily_message_limit: "" },
    },
  )

  useEffect(() => {
    if (!open) return

    form.reset({
      api_key: "",
      session: row.session ?? "",
      daily_message_limit:
        row.daily_message_limit == null ? "" : String(row.daily_message_limit),
    })
  }, [open, row, form])

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      const apiKey = values.api_key?.trim() ?? ""
      const session = values.session?.trim() ?? ""
      const limit = values.daily_message_limit?.trim() ?? ""

      const payload: ClinicGatewayPayload = {
        api_key: apiKey === "" ? null : apiKey,
        session: session === "" ? null : session,
        daily_message_limit: limit === "" ? null : Number(limit),
      }

      return apiPut<MutationResult>(
        `/central/whatsapp-gateway/clinics/${row.tenant_id}`,
        payload,
      )
    },
    onSuccess: (result) => {
      toast.success(
        result?.meta?.message ?? t("whatsapp_gateway.saved_clinic"),
      )
      void qc.invalidateQueries({
        queryKey: ["central-whatsapp-gateway-clinics"],
      })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent layout="form" className="max-md:h-fit">
        <DialogHeader>
          <DialogTitle>{t("whatsapp_gateway.manage")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {row.name} · {row.slug}. {t("whatsapp_gateway.manage_desc")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="flex min-h-0 flex-1 flex-col gap-4"
          >
            <DialogBody className="space-y-4">
              <FormInput
                control={form.control}
                name="api_key"
                type="password"
                autoComplete="new-password"
                label={t("whatsapp_gateway.api_key")}
                placeholder={t("whatsapp_gateway.api_key_placeholder")}
                description={t("whatsapp_gateway.api_key_input_hint")}
                inputClassName="font-mono"
              />
              <FormInput
                control={form.control}
                name="session"
                label={t("whatsapp_gateway.session")}
                placeholder={t("whatsapp_gateway.session_placeholder")}
                description={t("whatsapp_gateway.session_hint")}
                inputClassName="font-mono"
              />
              <FormInput
                control={form.control}
                name="daily_message_limit"
                type="number"
                min={1}
                step={1}
                label={t("whatsapp_gateway.daily_limit")}
                placeholder={t("whatsapp_gateway.daily_limit_placeholder")}
                description={t("whatsapp_gateway.daily_limit_hint")}
                inputClassName="tabular-nums"
              />
            </DialogBody>
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

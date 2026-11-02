import { useEffect } from "react"
import { z } from "zod"

import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export interface ContactValues {
  name: string
  phone: string
}

interface ContactDialogProps {
  open: boolean
  isSending: boolean
  serverErrors?: Record<string, string[]>
  onOpenChange: (open: boolean) => void
  onSubmit: (values: ContactValues) => void
}

function buildSchema(requiredMessage: string, phoneMessage: string) {
  return z.object({
    name: z.string().trim().min(1, requiredMessage).max(100),
    phone: z
      .string()
      .trim()
      .min(1, requiredMessage)
      .refine((value) => /^(\+?62|0)\d{8,13}$/.test(compactPhone(value)), phoneMessage),
  })
}

function compactPhone(value: string): string {
  return value.replace(/[\s\-().]/g, "")
}

/** Server menerima format internasional; nomor lokal 08xx diubah ke 62xx. */
function toApiPhone(value: string): string {
  const compact = compactPhone(value)

  return compact.startsWith("0") ? `62${compact.slice(1)}` : compact.replace(/^\+/, "")
}

/** Bagikan kontak sebagai kartu vCard lewat Cloud API. */
export function ContactDialog({
  open,
  isSending,
  serverErrors,
  onOpenChange,
  onSubmit,
}: ContactDialogProps) {
  const { t } = useTrans()
  const form = useForm(
    buildSchema(t("inbox.attach.contact.name_required"), t("inbox.attach.contact.phone_invalid")),
    { defaultValues: { name: "", phone: "" } },
  )

  useEffect(() => {
    if (open) form.reset({ name: "", phone: "" })
  }, [open, form])

  useEffect(() => {
    if (open) applyServerErrors(form, serverErrors)
  }, [form, open, serverErrors])

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next && !isSending) onOpenChange(false)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("inbox.attach.contact.title")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("inbox.attach.contact.hint")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) =>
              onSubmit({ name: values.name.trim(), phone: toApiPhone(values.phone) }),
            )}
            className="space-y-4"
          >
            <FormInput
              control={form.control}
              name="name"
              label={t("inbox.attach.contact.name")}
              placeholder={t("inbox.attach.contact.name_placeholder")}
              autoComplete="name"
              disabled={isSending}
              required
            />

            <FormInput
              control={form.control}
              name="phone"
              type="tel"
              label={t("inbox.attach.contact.phone")}
              placeholder="0812 3456 7890"
              description={t("inbox.attach.contact.phone_hint")}
              autoComplete="tel"
              inputClassName="tabular-nums"
              disabled={isSending}
              required
            />

            <DialogFooter>
              <FormSubmit loading={isSending}>
                {t("inbox.attach.contact.submit")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

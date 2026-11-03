import { useEffect, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"

import { FormAlert } from "#/components/forms/form-alert.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
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
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().trim().min(1).max(512),
  language: z.string().trim().min(2).max(12),
  category: z.enum(["MARKETING", "UTILITY"]),
  header_type: z.enum(["NONE", "TEXT", "IMAGE"]),
  header_text: z.string(),
  body: z.string().trim().min(1),
})

type Values = z.infer<typeof schema>

const EMPTY: Values = {
  name: "",
  language: "id",
  category: "MARKETING",
  header_type: "NONE",
  header_text: "",
  body: "",
}

/** Aturan nama template Meta: huruf kecil, angka, dan garis bawah. */
const NAME_PATTERN = /^[a-z0-9_]+$/

const PARAM_SLOT = /\{\{\s*(\d+)\s*\}\}/g

/** Nomor slot {{n}} yang benar-benar muncul di body, unik dan terurut. */
function placeholderNumbers(body: string): string[] {
  const found = new Set<string>()

  for (const match of body.matchAll(PARAM_SLOT)) {
    if (match[1]) found.add(match[1])
  }

  return [...found].sort((a, b) => Number(a) - Number(b))
}

interface WhatsappTemplateFormProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Form pembuatan template resmi Meta.
 *
 * Template baru tidak langsung terpakai: Meta meninjaunya lebih dulu, jadi
 * formulir ini menutup dengan pengingat status menunggu, bukan seolah pesan
 * sudah bisa dikirim begitu disimpan.
 */
export function WhatsappTemplateForm({
  tenant,
  open,
  onOpenChange,
}: WhatsappTemplateFormProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const form = useForm(schema, { defaultValues: EMPTY })
  const headerType = form.watch("header_type")
  const body = form.watch("body")
  const placeholders = placeholderNumbers(body ?? "")
  const [formError, setFormError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    form.reset(EMPTY)
    setFormError(null)
  }, [open, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost<{ data: unknown; meta?: { message?: string } }>(
        `/${tenant}/clinic/broadcasts/templates`,
        {
          name: values.name,
          language: values.language,
          category: values.category,
          header_type: values.header_type === "NONE" ? null : values.header_type,
          header_text: values.header_type === "TEXT" ? values.header_text.trim() : null,
          body: values.body,
        },
      ),
    onMutate: () => setFormError(null),
    onSuccess: () => {
      toast.success(t("broadcast.template_created"))
      // Daftar kelola dan pemilih broadcast berbagi awalan kunci yang sama,
      // jadi keduanya ikut diperbarui dalam satu invalidasi.
      qc.invalidateQueries({ queryKey: ["broadcast-templates"] })
      form.reset(EMPTY)
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      setFormError(err.message)
      toast.error(err.message)
    },
  })

  const submit = (values: Values) => {
    if (!NAME_PATTERN.test(values.name)) {
      form.setError("name", {
        type: "validate",
        message: t("broadcast.template_invalid_name"),
      })
      return
    }

    mutation.mutate(values)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] gap-0 overflow-hidden p-0 sm:max-w-lg">
        <DialogHeader className="border-b border-border/50 p-4">
          <DialogTitle>{t("broadcast.template_new")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("broadcast.template_manage_hint")}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(submit)} className="flex min-h-0 flex-col">
            <div className="max-h-[62dvh] min-h-0 flex-1 overflow-y-auto p-4">
              <div className="space-y-4">
                <FormAlert message={formError} />

                <FormInput
                  control={form.control}
                  name="name"
                  label={t("broadcast.template_name")}
                  description={t("broadcast.template_name_hint")}
                  placeholder="promo_bulan_ini"
                  required
                  inputClassName="font-mono"
                />

                <FormInput
                  control={form.control}
                  name="language"
                  label={t("broadcast.template_language_label")}
                  placeholder="id"
                  required
                  inputClassName="font-mono"
                />

                <FormSelect
                  control={form.control}
                  name="category"
                  label={t("broadcast.template_category")}
                  options={[
                    {
                      value: "MARKETING",
                      label: t("broadcast.template_category_marketing"),
                    },
                    {
                      value: "UTILITY",
                      label: t("broadcast.template_category_utility"),
                    },
                  ]}
                />

                <FormSelect
                  control={form.control}
                  name="header_type"
                  label={t("broadcast.template_header_type")}
                  options={[
                    { value: "NONE", label: t("broadcast.template_header_none") },
                    { value: "TEXT", label: t("broadcast.template_header_text") },
                    { value: "IMAGE", label: t("broadcast.template_header_image") },
                  ]}
                />

                {headerType === "TEXT" ? (
                  <FormInput
                    control={form.control}
                    name="header_text"
                    label={t("broadcast.template_header_text_label")}
                    required
                  />
                ) : null}

                <FormTextarea
                  control={form.control}
                  name="body"
                  label={t("broadcast.template_body")}
                  description={t("broadcast.template_body_hint")}
                  placeholder={"Halo {{1}}, ada penawaran dari {{2}} untuk kamu."}
                  rows={6}
                  required
                />

                {placeholders.length > 0 ? (
                  <div className="rounded-md border border-border/50 bg-muted/30 px-3 py-2.5">
                    <div className="flex flex-wrap items-center gap-1.5">
                      <span className="text-xs font-medium">
                        {t("broadcast.template_params")}
                      </span>
                      {placeholders.map((number) => (
                        <Badge
                          key={number}
                          variant="secondary"
                          className="font-mono text-xs font-normal tabular-nums"
                        >
                          {`{{${number}}}`}
                        </Badge>
                      ))}
                    </div>
                    <p className="mt-1 text-xs text-pretty text-muted-foreground">
                      {t("broadcast.template_examples_hint")}
                    </p>
                  </div>
                ) : null}
              </div>
            </div>

            <DialogFooter className="border-t border-border/50 p-4">
              <Button
                type="button"
                variant="outline"
                onClick={() => onOpenChange(false)}
                className="min-h-11 sm:min-h-10"
              >
                {t("general.cancel")}
              </Button>
              <FormSubmit
                loading={mutation.isPending}
                className="min-h-11 sm:min-h-10"
              >
                {t("general.save")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

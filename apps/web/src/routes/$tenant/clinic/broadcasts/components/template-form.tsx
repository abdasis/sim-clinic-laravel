import type { UseFormReturn } from "react-hook-form"
import { z } from "zod"

import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { SectionHeader } from "./section-header.tsx"

export const templateSchema = z.object({
  name: z.string().trim().min(1).max(512),
  language: z.string().trim().min(2).max(12),
  category: z.enum(["MARKETING", "UTILITY"]),
  header_type: z.enum(["NONE", "TEXT", "IMAGE"]),
  header_text: z.string(),
  body: z.string().trim().min(1),
})

export type TemplateValues = z.infer<typeof templateSchema>

export const TEMPLATE_DEFAULTS: TemplateValues = {
  name: "",
  language: "id",
  category: "MARKETING",
  header_type: "NONE",
  header_text: "",
  body: "",
}

/** Aturan nama template Meta: huruf kecil, angka, dan garis bawah. */
export const NAME_PATTERN = /^[a-z0-9_]+$/

const PARAM_SLOT = /\{\{\s*(\d+)\s*\}\}/g

/** Nomor slot {{n}} yang benar-benar muncul di body, unik dan terurut. */
export function placeholderNumbers(body: string): string[] {
  const found = new Set<string>()

  for (const match of body.matchAll(PARAM_SLOT)) {
    if (match[1]) found.add(match[1])
  }

  return [...found].sort((a, b) => Number(a) - Number(b))
}

/** Nilai formulir → payload API. Header kosong dikirim null, bukan "". */
export function templateToPayload(values: TemplateValues) {
  return {
    name: values.name,
    language: values.language,
    category: values.category,
    header_type: values.header_type === "NONE" ? null : values.header_type,
    header_text:
      values.header_type === "TEXT" ? values.header_text.trim() : null,
    body: values.body,
  }
}

interface TemplateFormFieldsProps {
  form: UseFormReturn<TemplateValues>
  disabled?: boolean
}

/**
 * Tiga seksi isian template: identitas, judul opsional, dan isi pesan.
 * Halaman pemanggil mengurus pratinjau, panduan, mutasi, dan footer.
 */
export function TemplateFormFields({
  form,
  disabled = false,
}: TemplateFormFieldsProps) {
  const { t } = useTrans()
  const headerType = form.watch("header_type")
  const placeholders = placeholderNumbers(form.watch("body") ?? "")

  return (
    <div className="space-y-4">
      <section className="rounded-lg border border-border/50 bg-card p-4 sm:p-5">
        <SectionHeader
          step={1}
          title={t("broadcast.template_section_identity")}
          hint={t("broadcast.template_section_identity_hint")}
        />
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <FormInput
            control={form.control}
            name="name"
            label={t("broadcast.template_name")}
            description={t("broadcast.template_name_hint")}
            placeholder="promo_bulanan"
            required
            disabled={disabled}
            className="sm:col-span-2"
            inputClassName="font-mono"
          />
          <FormInput
            control={form.control}
            name="language"
            label={t("broadcast.template_language_label")}
            placeholder="id"
            required
            disabled={disabled}
            inputClassName="font-mono"
          />
          <FormSelect
            control={form.control}
            name="category"
            label={t("broadcast.template_category")}
            description={t("broadcast.template_category_hint")}
            disabled={disabled}
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
        </div>
      </section>

      <section className="rounded-lg border border-border/50 bg-card p-4 sm:p-5">
        <SectionHeader
          step={2}
          title={t("broadcast.template_section_header")}
          hint={t("broadcast.template_section_header_hint")}
        />
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
          <FormSelect
            control={form.control}
            name="header_type"
            label={t("broadcast.template_header_type")}
            disabled={disabled}
            className={headerType === "TEXT" ? undefined : "sm:col-span-2"}
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
              disabled={disabled}
            />
          ) : null}
        </div>
      </section>

      <section className="rounded-lg border border-border/50 bg-card p-4 sm:p-5">
        <SectionHeader
          step={3}
          title={t("broadcast.template_section_body")}
          hint={t("broadcast.template_section_body_hint")}
        />
        <div className="mt-4 space-y-3">
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
      </section>
    </div>
  )
}

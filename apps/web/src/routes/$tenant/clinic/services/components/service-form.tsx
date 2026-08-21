import type { UseFormReturn } from "react-hook-form"
import { z } from "zod"

import { FormInput } from "#/components/forms/form-input.tsx"
import { FormRichText } from "#/components/forms/form-rich-text.tsx"
import { FormSection } from "#/components/forms/form-section.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { useCategoryOptions } from "#/hooks/use-category-options.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"

export const serviceSchema = z.object({
  name: z.string().min(1),
  // "" berarti tanpa kategori; dikonversi ke null saat submit.
  category_id: z.string().optional(),
  description: z.string().optional(),
  price: z.coerce.number().gte(0),
  duration_minutes: z.coerce.number().int().gte(1).lte(600),
  status: z.string().optional(),
  // Dokumen Tiptap. Tidak divalidasi bentuknya: isinya datang dari editor,
  // dan skema node-nya milik Tiptap, bukan milik formulir ini.
  knowledge: z.any().optional(),
})

export type ServiceValues = z.infer<typeof serviceSchema>

export const serviceDefaults: ServiceValues = {
  name: "",
  category_id: "",
  description: "",
  price: 0,
  duration_minutes: 30,
  status: "active",
  knowledge: null,
}

/** Bentuk satu layanan seperti yang dikirim API. */
export interface ServiceRow {
  id: number
  name: string
  category_id?: number | null
  description?: string | null
  knowledge?: RichTextDoc | null
  price: string | number
  duration_minutes: number
  status: string
}

/** Isi API → nilai formulir. Angka jadi number, id jadi string untuk select. */
export function serviceToValues(service: ServiceRow): ServiceValues {
  return {
    name: service.name,
    category_id: service.category_id ? String(service.category_id) : "",
    description: service.description ?? "",
    price: Number(service.price),
    duration_minutes: service.duration_minutes ?? 30,
    status: service.status,
    knowledge: service.knowledge ?? null,
  }
}

/**
 * Nilai formulir → payload API.
 *
 * Kategori dikirim sebagai id atau null, bukan string kosong: backend
 * memvalidasinya sebagai `nullable|exists`, dan "" bukan keduanya.
 */
export function serviceToPayload(values: ServiceValues) {
  const { category_id, ...rest } = values

  return { ...rest, category_id: category_id ? Number(category_id) : null }
}

export function ServiceFormFields({
  form,
  tenant,
  disabled,
}: {
  form: UseFormReturn<ServiceValues>
  tenant: string
  disabled?: boolean
}) {
  const { t } = useTrans()
  const categories = useCategoryOptions(tenant, "service", {
    emptyLabel: t("category.none"),
  })

  return (
    <div className="space-y-4">
      <FormSection
        title={t("service.section_identity")}
        description={t("service.section_identity_desc")}
      >
        <FormInput
          control={form.control}
          name="name"
          label={t("service.name")}
          required
          className="sm:col-span-2"
        />
        <FormSelect
          control={form.control}
          name="category_id"
          label={t("service.category")}
          options={categories.options}
        />
        <FormSelect
          control={form.control}
          name="status"
          label={t("service.status")}
          options={[
            { label: t("service.status_active"), value: "active" },
            { label: t("service.status_archived"), value: "archived" },
          ]}
        />
        <FormTextarea
          control={form.control}
          name="description"
          label={t("service.description")}
          className="sm:col-span-2"
        />
      </FormSection>

      <FormSection
        title={t("service.section_pricing")}
        description={t("service.section_pricing_desc")}
      >
        <FormInput
          control={form.control}
          name="price"
          label={t("service.price")}
          type="number"
          min={0}
          required
          inputClassName="tabular-nums"
        />
        <FormInput
          control={form.control}
          name="duration_minutes"
          label={t("service.duration_minutes")}
          tooltip={t("service.duration_minutes_hint")}
          type="number"
          min={1}
          max={600}
          step={1}
          required
          inputClassName="tabular-nums"
        />
      </FormSection>

      {/* Satu kolom penuh: teksnya panjang dan editornya butuh lebar. */}
      <FormSection
        title={t("service.knowledge")}
        description={t("service.knowledge_hint")}
        className="sm:grid-cols-1"
      >
        <FormRichText
          form={form}
          name="knowledge"
          placeholder={t("service.knowledge_placeholder")}
          disabled={disabled}
        />
      </FormSection>
    </div>
  )
}

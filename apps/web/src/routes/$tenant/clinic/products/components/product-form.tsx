import type { UseFormReturn } from "react-hook-form"
import { z } from "zod"

import { FormInput } from "#/components/forms/form-input.tsx"
import { FormRichText } from "#/components/forms/form-rich-text.tsx"
import { FormSection } from "#/components/forms/form-section.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { useCategoryOptions } from "#/hooks/use-category-options.ts"
import { useUnitOptions } from "#/hooks/use-unit-options.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"

// Saldo stok sengaja tidak ada di form — hanya berubah lewat mutasi stok.
export const productSchema = z.object({
  name: z.string().min(1),
  type: z.string(),
  category_id: z.string().optional(),
  unit_id: z.string().optional(),
  min_threshold: z.coerce.number().gte(0),
  price: z.coerce.number().gte(0),
  // Dokumen Tiptap. Tidak divalidasi bentuknya: isinya datang dari editor,
  // dan skema node-nya milik Tiptap, bukan milik formulir ini.
  knowledge: z.any().optional(),
})

export type ProductValues = z.infer<typeof productSchema>

export const productDefaults: ProductValues = {
  name: "",
  type: "retail",
  category_id: "",
  unit_id: "",
  min_threshold: 0,
  price: 0,
  knowledge: null,
}

/** Bentuk satu produk seperti yang dikirim API. */
export interface ProductRow {
  id: number
  name: string
  type?: string | null
  category_id?: number | null
  unit_id?: number | null
  min_threshold: number
  price: string | number
  status: string
  knowledge?: RichTextDoc | null
}

export function productToValues(product: ProductRow): ProductValues {
  return {
    name: product.name,
    type: product.type ?? "retail",
    category_id: product.category_id ? String(product.category_id) : "",
    unit_id: product.unit_id ? String(product.unit_id) : "",
    min_threshold: Number(product.min_threshold),
    price: Number(product.price),
    knowledge: product.knowledge ?? null,
  }
}

/**
 * Nilai formulir → payload API.
 *
 * Kategori dan satuan dikirim sebagai id atau null, bukan string kosong:
 * backend memvalidasi keduanya `nullable|exists`, dan "" bukan keduanya.
 */
export function productToPayload(values: ProductValues) {
  const { category_id, unit_id, ...rest } = values

  return {
    ...rest,
    category_id: category_id ? Number(category_id) : null,
    unit_id: unit_id ? Number(unit_id) : null,
  }
}

export function ProductFormFields({
  form,
  tenant,
  disabled,
}: {
  form: UseFormReturn<ProductValues>
  tenant: string
  disabled?: boolean
}) {
  const { t } = useTrans()
  const isRetail = form.watch("type") !== "consumable"

  const categories = useCategoryOptions(tenant, "product", {
    emptyLabel: t("category.none"),
  })
  const units = useUnitOptions(tenant, { emptyLabel: t("unit.none") })

  return (
    <div className="space-y-4">
      <FormSection
        title={t("product.section_identity")}
        description={t("product.section_identity_desc")}
      >
        <FormInput
          control={form.control}
          name="name"
          label={t("product.name")}
          required
          className="sm:col-span-2"
        />
        <FormSelect
          control={form.control}
          name="type"
          label={t("product.type")}
          options={[
            { label: t("product.type_retail"), value: "retail" },
            { label: t("product.type_consumable"), value: "consumable" },
          ]}
          description={!isRetail ? t("product.type_consumable_hint") : undefined}
        />
        <FormSelect
          control={form.control}
          name="category_id"
          label={t("product.category")}
          options={categories.options}
        />
      </FormSection>

      <FormSection
        title={t("product.section_stock")}
        description={t("product.section_stock_desc")}
      >
        <FormSelect
          control={form.control}
          name="unit_id"
          label={t("product.unit")}
          options={units.options}
        />
        <FormInput
          control={form.control}
          name="min_threshold"
          label={t("product.min_threshold")}
          type="number"
          min={0}
          inputClassName="tabular-nums"
        />
        {/* Bahan pakai tidak dijual, jadi harga jualnya tidak diminta —
            memaksa mengisinya hanya melahirkan angka karangan. */}
        {isRetail ? (
          <FormInput
            control={form.control}
            name="price"
            label={t("product.price")}
            type="number"
            min={0}
            inputClassName="tabular-nums"
          />
        ) : null}
      </FormSection>

      {/* Satu kolom penuh: teksnya panjang dan editornya butuh lebar. */}
      <FormSection
        title={t("product.knowledge")}
        description={t("product.knowledge_hint")}
        className="sm:grid-cols-1"
      >
        <FormRichText
          form={form}
          name="knowledge"
          placeholder={t("product.knowledge_placeholder")}
          disabled={disabled}
        />
      </FormSection>
    </div>
  )
}

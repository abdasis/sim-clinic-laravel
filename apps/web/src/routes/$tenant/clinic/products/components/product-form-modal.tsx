import { useEffect } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useCategoryOptions } from "#/hooks/use-category-options.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

// Saldo stok sengaja tidak ada di form — hanya berubah lewat mutasi stok.
const schema = z.object({
  name: z.string().min(1),
  type: z.string(),
  category_id: z.string().optional(),
  unit: z.string().min(1),
  min_threshold: z.coerce.number().gte(0),
  price: z.coerce.number().gte(0),
})

type Values = z.infer<typeof schema>

export interface ProductFormValues {
  id: number
  name: string
  type?: string | null
  category_id?: number | null
  unit: string
  min_threshold: number
  price: string | number
  status: string
}

interface ProductFormModalProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  product?: ProductFormValues
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ProductFormModal({
  tenant,
  product,
  open,
  onOpenChange,
}: ProductFormModalProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = product !== undefined

  const form = useForm(schema, {
    defaultValues: {
      name: "",
      type: "retail",
      category_id: "",
      unit: "",
      min_threshold: 0,
      price: 0,
    },
  })

  useEffect(() => {
    if (!open) return

    form.reset(
      product
        ? {
            name: product.name,
            type: product.type ?? "retail",
            category_id: product.category_id ? String(product.category_id) : "",
            unit: product.unit,
            min_threshold: Number(product.min_threshold),
            price: Number(product.price),
          }
        : {
            name: "",
            type: "retail",
            category_id: "",
            unit: "",
            min_threshold: 0,
            price: 0,
          },
    )
  }, [open, product, form])

  const isRetail = form.watch("type") !== "consumable"

  const categories = useCategoryOptions(tenant, "product", {
    enabled: open,
    emptyLabel: t("category.none"),
  })

  const mutation = useMutation({
    mutationFn: ({ category_id, ...values }: Values) => {
      // Kategori kini id entitas; string kosong berarti tanpa kategori.
      const payload = { ...values, category_id: category_id ? Number(category_id) : null }

      return isEdit
        ? apiPut(`/${tenant}/clinic/products/${product.id}`, payload)
        : apiPost(`/${tenant}/clinic/products`, payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? t("product.updated") : t("product.created"))
      qc.invalidateQueries({ queryKey: ["products"] })
      onOpenChange(false)
      form.reset()
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
          <DialogTitle>
            {isEdit ? t("product.edit") : t("product.add")}
          </DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormInput
              control={form.control}
              name="name"
              label={t("product.name")}
            />
            <FormSelect
              control={form.control}
              name="type"
              label={t("product.type")}
              options={[
                { label: t("product.type_retail"), value: "retail" },
                { label: t("product.type_consumable"), value: "consumable" },
              ]}
            />
            {!isRetail ? (
              <p className="-mt-2 text-xs leading-relaxed text-muted-foreground">
                {t("product.type_consumable_hint")}
              </p>
            ) : null}
            <FormSelect
              control={form.control}
              name="category_id"
              label={t("product.category")}
              options={categories.options}
            />
            <FormInput
              control={form.control}
              name="unit"
              label={t("product.unit")}
            />
            <FormInput
              control={form.control}
              name="min_threshold"
              label={t("product.min_threshold")}
              type="number"
            />
            {/* Bahan pakai tidak dijual, jadi harga jualnya tidak diminta —
                memaksa mengisinya hanya melahirkan angka karangan. */}
            {isRetail ? (
              <FormInput
                control={form.control}
                name="price"
                label={t("product.price")}
                type="number"
              />
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

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
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

// Saldo stok sengaja tidak ada di form — hanya berubah lewat mutasi stok.
const schema = z.object({
  name: z.string().min(1),
  category: z.string().optional(),
  unit: z.string().min(1),
  min_threshold: z.coerce.number().gte(0),
  price: z.coerce.number().gte(0),
})

type Values = z.infer<typeof schema>

export interface ProductFormValues {
  id: number
  name: string
  category?: string | null
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
    defaultValues: { name: "", category: "", unit: "", min_threshold: 0, price: 0 },
  })

  useEffect(() => {
    if (!open) return

    form.reset(
      product
        ? {
            name: product.name,
            category: product.category ?? "",
            unit: product.unit,
            min_threshold: Number(product.min_threshold),
            price: Number(product.price),
          }
        : { name: "", category: "", unit: "", min_threshold: 0, price: 0 },
    )
  }, [open, product, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      isEdit
        ? apiPut(`/${tenant}/clinic/products/${product.id}`, values)
        : apiPost(`/${tenant}/clinic/products`, values),
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
              name="category"
              label={t("product.category")}
              options={[
                { label: "—", value: "" },
                { label: "Facial Wash", value: "facial_wash" },
                { label: "Toner", value: "toner" },
                { label: "Sunscreen", value: "sunscreen" },
                { label: "Serum", value: "serum" },
                { label: "Night Cream", value: "night_cream" },
              ]}
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
            <FormInput
              control={form.control}
              name="price"
              label={t("product.price")}
              type="number"
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

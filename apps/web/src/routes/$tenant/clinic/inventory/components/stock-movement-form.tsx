import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface ProductOption {
  id: number
  name: string
}

const schema = z.object({
  product_id: z.string().min(1),
  type: z.string().min(1),
  quantity: z.coerce.number().gte(0),
  note: z.string().optional(),
})

type Values = z.infer<typeof schema>

interface Props {
  tenant: string
  onProductChange: (productId: string) => void
}

export function StockMovementForm({ tenant, onProductChange }: Props) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const { data: products } = useQuery({
    queryKey: ["products", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: ProductOption[] }>(`/${tenant}/clinic/products`, {
        per_page: 100,
      }),
  })

  const form = useForm(schema, {
    defaultValues: { product_id: "", type: "in", quantity: 0, note: "" },
  })

  const selectedProduct = form.watch("product_id")
  useEffect(() => {
    onProductChange(selectedProduct)
  }, [selectedProduct, onProductChange])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost(`/${tenant}/clinic/products/${values.product_id}/stock-movements`, {
        type: values.type,
        quantity: values.quantity,
        note: values.note,
      }),
    onSuccess: (_data, values) => {
      toast.success(t("inventory.movement_recorded"))
      qc.invalidateQueries({
        queryKey: ["stock-movements", tenant, values.product_id],
      })
      qc.invalidateQueries({ queryKey: ["products"] })
      form.reset({ product_id: values.product_id, type: "in", quantity: 0, note: "" })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const productOptions = (products?.data ?? []).map((p) => ({
    label: p.name,
    value: String(p.id),
  }))

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
        className="max-w-md space-y-4"
      >
        <FormSelect
          control={form.control}
          name="product_id"
          label={t("inventory.product")}
          placeholder={t("general.select")}
          options={productOptions}
        />
        <FormSelect
          control={form.control}
          name="type"
          label={t("inventory.type")}
          options={[
            { label: t("clinic.stock_movement_type.in"), value: "in" },
            {
              label: t("clinic.stock_movement_type.out_manual"),
              value: "out_manual",
            },
          ]}
        />
        <FormInput
          control={form.control}
          name="quantity"
          label={t("inventory.quantity")}
          type="number"
        />
        <FormTextarea
          control={form.control}
          name="note"
          label={t("inventory.note")}
        />
        <FormSubmit loading={mutation.isPending}>{t("general.save")}</FormSubmit>
      </form>
    </Form>
  )
}

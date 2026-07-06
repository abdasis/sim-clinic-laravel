import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { Button } from "#/components/ui/button.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  unit: z.string().min(1),
  stock_balance: z.coerce.number().gte(0),
  min_threshold: z.coerce.number().gte(0),
  price: z.coerce.number().gte(0),
})

type Values = z.infer<typeof schema>

export function ProductFormModal({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const qc = useQueryClient()
  const form = useForm(schema, {
    defaultValues: {
      name: "",
      unit: "",
      stock_balance: 0,
      min_threshold: 0,
      price: 0,
    },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost(`/${tenant}/clinic/products`, values),
    onSuccess: () => {
      toast.success(t("product.created"))
      qc.invalidateQueries({ queryKey: ["products"] })
      setOpen(false)
      form.reset()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button>{t("product.add")}</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("product.add")}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormInput control={form.control} name="name" label={t("product.name")} />
            <FormInput control={form.control} name="unit" label={t("product.unit")} />
            <FormInput
              control={form.control}
              name="stock_balance"
              label={t("product.stock_balance")}
              type="number"
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
              <FormSubmit loading={mutation.isPending}>{t("general.save")}</FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

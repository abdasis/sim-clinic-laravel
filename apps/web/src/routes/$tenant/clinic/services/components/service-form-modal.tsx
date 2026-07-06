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
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
  price: z.coerce.number().gte(0),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export function ServiceFormModal({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const qc = useQueryClient()
  const form = useForm(schema, {
    defaultValues: { name: "", description: "", price: 0, status: "active" },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost(`/${tenant}/clinic/services`, values),
    onSuccess: () => {
      toast.success(t("service.created"))
      qc.invalidateQueries({ queryKey: ["services"] })
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
        <Button>{t("service.add")}</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("service.add")}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormInput control={form.control} name="name" label={t("service.name")} />
            <FormTextarea
              control={form.control}
              name="description"
              label={t("service.description")}
            />
            <FormInput
              control={form.control}
              name="price"
              label={t("service.price")}
              type="number"
            />
            <FormSelect
              control={form.control}
              name="status"
              label={t("service.status")}
              options={[
                { label: t("clinic.service_status.active"), value: "active" },
                { label: t("clinic.service_status.archived"), value: "archived" },
              ]}
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

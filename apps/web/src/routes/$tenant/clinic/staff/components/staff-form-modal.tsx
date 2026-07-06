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
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  email: z.string().email(),
  clinic_role: z.string().min(1),
  password: z.string().min(1),
})

type Values = z.infer<typeof schema>

export function StaffFormModal({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const qc = useQueryClient()
  const form = useForm(schema, {
    defaultValues: { name: "", email: "", clinic_role: "cashier", password: "" },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) => apiPost(`/${tenant}/clinic/staff`, values),
    onSuccess: () => {
      toast.success(t("staff.created"))
      qc.invalidateQueries({ queryKey: ["staff"] })
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
        <Button>{t("staff.add")}</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("staff.add")}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormInput control={form.control} name="name" label={t("staff.name")} />
            <FormInput
              control={form.control}
              name="email"
              label={t("staff.email")}
              type="email"
            />
            <FormSelect
              control={form.control}
              name="clinic_role"
              label={t("staff.clinic_role")}
              options={[
                { label: t("clinic.role.admin"), value: "admin" },
                { label: t("clinic.role.doctor"), value: "doctor" },
                { label: t("clinic.role.therapist"), value: "therapist" },
                { label: t("clinic.role.cashier"), value: "cashier" },
              ]}
            />
            <FormInput
              control={form.control}
              name="password"
              label={t("staff.password")}
              type="password"
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

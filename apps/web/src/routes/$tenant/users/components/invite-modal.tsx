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
  email: z.string().email(),
  role: z.enum(["member", "tenant_admin"]),
  clinic_role: z.string().optional(),
})

type Values = z.infer<typeof schema>

interface InviteResponse {
  data: { token: string }
}

export function InviteModal({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const qc = useQueryClient()
  const form = useForm(schema, {
    defaultValues: { email: "", role: "member", clinic_role: "" },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost<InviteResponse>(`/${tenant}/users/invite`, {
        ...values,
        // Kosong berarti tanpa peran klinik; jangan kirim string kosong.
        clinic_role: values.clinic_role || undefined,
      }),
    onSuccess: (res) => {
      toast.success(t("tenant.invited"), { description: res.data.token })
      qc.invalidateQueries({ queryKey: ["users"] })
      qc.invalidateQueries({ queryKey: ["invitations"] })
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
        <Button>{t("tenant.invite")}</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("tenant.invite")}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormInput
              control={form.control}
              name="email"
              label={t("tenant.email")}
              type="email"
            />
            <FormSelect
              control={form.control}
              name="role"
              label={t("tenant.user_role")}
              options={[
                { label: t("tenant.role.member"), value: "member" },
                { label: t("tenant.role.tenant_admin"), value: "tenant_admin" },
              ]}
            />
            <FormSelect
              control={form.control}
              name="clinic_role"
              label={t("staff.clinic_role")}
              options={[
                { label: t("tenant.no_clinic_role"), value: "" },
                { label: t("clinic.role.admin"), value: "admin" },
                { label: t("clinic.role.doctor"), value: "doctor" },
                { label: t("clinic.role.therapist"), value: "therapist" },
                { label: t("clinic.role.cashier"), value: "cashier" },
              ]}
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

import { useEffect } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Button } from "#/components/ui/button.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { StaffRow } from "../index.tsx"

const schema = z.object({
  name: z.string().min(1),
  email: z.string().email(),
  clinic_role: z.string().min(1),
})

type Values = z.infer<typeof schema>

const ROLE_VALUES = ["admin", "doctor", "therapist", "cashier"] as const

interface StaffEditModalProps {
  tenant: string
  staff: StaffRow
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Ubah data staf sekaligus perannya. Kata sandi tidak ikut di sini — menyetel
 * ulang sandi orang lain adalah tindakan yang berbeda dan pantas punya
 * konfirmasinya sendiri.
 */
export function StaffEditModal({
  tenant,
  staff,
  open,
  onOpenChange,
}: StaffEditModalProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const form = useForm(schema, {
    defaultValues: {
      name: staff.name,
      email: staff.email,
      clinic_role: staff.clinic_role,
    },
  })

  useEffect(() => {
    if (!open) return

    form.reset({
      name: staff.name,
      email: staff.email,
      clinic_role: staff.clinic_role,
    })
  }, [open, staff, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/staff/${staff.id}`, values),
    onSuccess: () => {
      toast.success(t("staff.updated"))
      qc.invalidateQueries({ queryKey: ["staff"] })
      onOpenChange(false)
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
          <DialogTitle>{t("staff.edit")}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <FormInput
              control={form.control}
              name="name"
              label={t("staff.name")}
            />
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
              options={ROLE_VALUES.map((value) => ({
                value,
                label: t(`clinic.role.${value}`),
              }))}
            />
            <div className="flex items-center justify-end gap-2">
              <Button
                type="button"
                variant="ghost"
                disabled={mutation.isPending}
                onClick={() => onOpenChange(false)}
              >
                {t("general.cancel")}
              </Button>
              <FormSubmit loading={mutation.isPending}>
                {t("general.save")}
              </FormSubmit>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

import { useEffect, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormAlert } from "#/components/forms/form-alert.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1).max(50),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface UnitFormValues {
  id: number
  name: string
  status: string
}

interface UnitFormDialogProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  unit?: UnitFormValues
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Satu dialog untuk tambah dan ubah satuan — bedanya hanya prefill dan
 * method HTTP.
 */
export function UnitFormDialog({
  tenant,
  unit,
  open,
  onOpenChange,
}: UnitFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = unit !== undefined
  const [failure, setFailure] = useState<string | null>(null)

  const form = useForm(schema, {
    defaultValues: { name: "", status: "active" },
  })

  useEffect(() => {
    if (!open) return

    setFailure(null)
    form.reset(
      unit
        ? { name: unit.name, status: unit.status }
        : { name: "", status: "active" },
    )
  }, [open, unit, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      isEdit
        ? apiPut(`/${tenant}/clinic/units/${unit.id}`, values)
        : apiPost(`/${tenant}/clinic/units`, values),
    onSuccess: () => {
      setFailure(null)
      toast.success(isEdit ? t("unit.updated") : t("unit.created"))
      qc.invalidateQueries({ queryKey: ["units"] })
      // Daftar produk menampilkan nama satuannya, jadi ikut disegarkan.
      qc.invalidateQueries({ queryKey: ["products"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      setFailure(err.errors ? null : err.message)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? t("unit.edit") : t("unit.add")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("unit.empty_desc")}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <FormAlert message={failure} />

            <FormInput
              control={form.control}
              name="name"
              label={t("unit.name")}
              required
            />

            <FormSelect
              control={form.control}
              name="status"
              label={t("unit.status")}
              options={[
                { value: "active", label: t("unit.status_active") },
                { value: "archived", label: t("unit.status_archived") },
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

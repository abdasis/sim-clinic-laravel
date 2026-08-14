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
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
  price: z.coerce.number().gte(0),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface ServiceFormValues {
  id: number
  name: string
  description?: string | null
  price: string | number
  status: string
}

interface ServiceFormDialogProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  service?: ServiceFormValues
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Satu dialog untuk tambah dan ubah layanan — bedanya hanya prefill dan
 * method HTTP, jadi tidak perlu dua komponen yang nyaris sama.
 */
export function ServiceFormDialog({
  tenant,
  service,
  open,
  onOpenChange,
}: ServiceFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = service !== undefined

  const form = useForm(schema, {
    defaultValues: { name: "", description: "", price: 0, status: "active" },
  })

  // Prefill saat dialog dibuka supaya data terbaru yang dipakai, bukan
  // nilai basi dari render sebelumnya.
  useEffect(() => {
    if (!open) return

    form.reset(
      service
        ? {
            name: service.name,
            description: service.description ?? "",
            price: Number(service.price),
            status: service.status,
          }
        : { name: "", description: "", price: 0, status: "active" },
    )
  }, [open, service, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      isEdit
        ? apiPut(`/${tenant}/clinic/services/${service.id}`, values)
        : apiPost(`/${tenant}/clinic/services`, values),
    onSuccess: () => {
      toast.success(isEdit ? t("service.updated") : t("service.created"))
      qc.invalidateQueries({ queryKey: ["services"] })
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
            {isEdit ? t("service.edit") : t("service.add")}
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
              label={t("service.name")}
            />
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
                { label: t("service.status_active"), value: "active" },
                { label: t("service.status_archived"), value: "archived" },
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

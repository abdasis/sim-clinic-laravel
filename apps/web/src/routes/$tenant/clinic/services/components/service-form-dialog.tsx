import { useEffect, useState } from "react"
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
import { FormAlert } from "#/components/forms/form-alert.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useCategoryOptions } from "#/hooks/use-category-options.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  category_id: z.string().optional(),
  description: z.string().optional(),
  price: z.coerce.number().gte(0),
  duration_minutes: z.coerce.number().int().gte(1).lte(600),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface ServiceFormValues {
  id: number
  name: string
  category_id?: number | null
  description?: string | null
  price: string | number
  duration_minutes: number
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
  // Kegagalan yang bukan milik satu field: izin ditolak, server bermasalah.
  const [failure, setFailure] = useState<string | null>(null)

  const form = useForm(schema, {
    defaultValues: {
      name: "",
      category_id: "",
      description: "",
      price: 0,
      duration_minutes: 30,
      status: "active",
    },
  })

  // Prefill saat dialog dibuka supaya data terbaru yang dipakai, bukan
  // nilai basi dari render sebelumnya.
  useEffect(() => {
    if (!open) return

    setFailure(null)
    form.reset(
      service
        ? {
            name: service.name,
            category_id: service.category_id
              ? String(service.category_id)
              : "",
            description: service.description ?? "",
            price: Number(service.price),
            duration_minutes: service.duration_minutes ?? 30,
            status: service.status,
          }
        : {
            name: "",
            category_id: "",
            description: "",
            price: 0,
            duration_minutes: 30,
            status: "active",
          },
    )
  }, [open, service, form])

  const categories = useCategoryOptions(tenant, "service", {
    enabled: open,
    emptyLabel: t("category.none"),
  })

  const mutation = useMutation({
    mutationFn: ({ category_id, ...values }: Values) => {
      // Kategori kini id entitas; string kosong berarti tanpa kategori.
      const payload = { ...values, category_id: category_id ? Number(category_id) : null }

      return isEdit
        ? apiPut(`/${tenant}/clinic/services/${service.id}`, payload)
        : apiPost(`/${tenant}/clinic/services`, payload)
    },
    onSuccess: () => {
      setFailure(null)
      toast.success(isEdit ? t("service.updated") : t("service.created"))
      qc.invalidateQueries({ queryKey: ["services"] })
      onOpenChange(false)
      form.reset()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      // Error per-field sudah tampil di bawah masing-masing input; sisanya
      // tidak punya tempat, jadi ditampilkan di kepala form.
      setFailure(err.errors ? null : err.message)
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
            <FormAlert message={failure} />
            <FormInput
              control={form.control}
              name="name"
              label={t("service.name")}
            />
            <FormSelect
              control={form.control}
              name="category_id"
              label={t("service.category")}
              options={categories.options}
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
            <FormInput
              control={form.control}
              name="duration_minutes"
              label={t("service.duration_minutes")}
              tooltip={t("service.duration_minutes_hint")}
              type="number"
              min={1}
              max={600}
              step={1}
              required
              inputClassName="tabular-nums"
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

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
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { CategoryType } from "#/hooks/use-category-options.ts"

const schema = z.object({
  name: z.string().min(1).max(100),
  type: z.string().min(1),
  description: z.string().optional(),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface CategoryFormValues {
  id: number
  name: string
  type: CategoryType
  description?: string | null
  status: string
}

interface CategoryFormDialogProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  category?: CategoryFormValues
  /** Jenis yang sedang dilihat, dipakai sebagai nilai awal saat menambah. */
  defaultType?: CategoryType
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Satu dialog untuk tambah dan ubah kategori — bedanya hanya prefill dan
 * method HTTP.
 */
export function CategoryFormDialog({
  tenant,
  category,
  defaultType = "service",
  open,
  onOpenChange,
}: CategoryFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = category !== undefined
  const [failure, setFailure] = useState<string | null>(null)

  const form = useForm(schema, {
    defaultValues: {
      name: "",
      type: defaultType,
      description: "",
      status: "active",
    },
  })

  useEffect(() => {
    if (!open) return

    setFailure(null)
    form.reset(
      category
        ? {
            name: category.name,
            type: category.type,
            description: category.description ?? "",
            status: category.status,
          }
        : { name: "", type: defaultType, description: "", status: "active" },
    )
  }, [open, category, defaultType, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      isEdit
        ? apiPut(`/${tenant}/clinic/categories/${category.id}`, values)
        : apiPost(`/${tenant}/clinic/categories`, values),
    onSuccess: () => {
      setFailure(null)
      toast.success(isEdit ? t("category.updated") : t("category.created"))
      qc.invalidateQueries({ queryKey: ["categories"] })
      // Katalog ikut menampilkan nama kategori, jadi daftarnya perlu segar.
      qc.invalidateQueries({ queryKey: ["services"] })
      qc.invalidateQueries({ queryKey: ["products"] })
      qc.invalidateQueries({ queryKey: ["expenses"] })
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
          <DialogTitle>
            {isEdit ? t("category.edit") : t("category.add")}
          </DialogTitle>
          <DialogDescription className="text-pretty">
            {t("category.empty_desc")}
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
              label={t("category.name")}
              required
            />

            <FormSelect
              control={form.control}
              name="type"
              label={t("category.type")}
              required
              // Memindahkan kategori antar jenis akan melepasnya dari item
              // yang sudah memakainya, jadi jenisnya dikunci setelah dibuat.
              disabled={isEdit}
              description={isEdit ? t("category.type_locked") : undefined}
              options={[
                { value: "service", label: t("category.type_service") },
                { value: "product", label: t("category.type_product") },
                { value: "expense", label: t("category.type_expense") },
              ]}
            />

            <FormTextarea
              control={form.control}
              name="description"
              label={t("category.description")}
            />

            <FormSelect
              control={form.control}
              name="status"
              label={t("category.status")}
              options={[
                { value: "active", label: t("service.status_active") },
                { value: "archived", label: t("service.status_archived") },
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

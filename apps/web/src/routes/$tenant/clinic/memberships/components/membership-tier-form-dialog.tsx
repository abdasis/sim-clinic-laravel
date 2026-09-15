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
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface MembershipTierFormValues {
  id: number
  name: string
  description?: string | null
  status: string
}

interface MembershipTierFormDialogProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  tier?: MembershipTierFormValues
  open: boolean
  onOpenChange: (open: boolean) => void
}

const EMPTY: Values = {
  name: "",
  description: "",
  status: "active",
}

/** Satu dialog untuk tambah dan ubah tingkat member. */
export function MembershipTierFormDialog({
  tenant,
  tier,
  open,
  onOpenChange,
}: MembershipTierFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = tier !== undefined

  const form = useForm(schema, { defaultValues: EMPTY })

  useEffect(() => {
    if (!open) return

    form.reset(
      tier
        ? {
            name: tier.name,
            description: tier.description ?? "",
            status: tier.status,
          }
        : EMPTY,
    )
  }, [open, tier, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      isEdit
        ? apiPut(`/${tenant}/clinic/membership-tiers/${tier.id}`, values)
        : apiPost(`/${tenant}/clinic/membership-tiers`, values),
    onSuccess: () => {
      toast.success(isEdit ? t("membership.updated") : t("membership.created"))
      qc.invalidateQueries({ queryKey: ["membership-tiers"] })
      // Formulir pasien memuat daftar tingkat lewat kueri yang sama.
      qc.invalidateQueries({ queryKey: ["patients"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] gap-0 overflow-hidden p-0 sm:max-w-lg">
        <DialogHeader className="border-b border-border/50 p-4">
          <DialogTitle>
            {isEdit ? t("membership.edit") : t("membership.add")}
          </DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="flex min-h-0 flex-col"
          >
            <div className="max-h-[62dvh] min-h-0 flex-1 overflow-y-auto">
              <div className="space-y-4 p-4">
                <FormInput
                  control={form.control}
                  name="name"
                  label={t("membership.tier_name")}
                  required
                />

                <FormTextarea
                  control={form.control}
                  name="description"
                  label={t("membership.description")}
                />

                <FormSelect
                  control={form.control}
                  name="status"
                  label={t("promo.status")}
                  options={[
                    { label: t("membership.status.active"), value: "active" },
                    { label: t("membership.status.inactive"), value: "inactive" },
                  ]}
                />
              </div>
            </div>

            <DialogFooter className="border-t border-border/50 p-4">
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

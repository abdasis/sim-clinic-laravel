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
import { FormSwitch } from "#/components/forms/form-switch.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
  discount_type: z.string().min(1),
  discount_value: z.coerce.number().gt(0),
  stacks_with_promo: z.boolean().optional(),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface MembershipTierFormValues {
  id: number
  name: string
  description?: string | null
  discount_type: string
  discount_value: string | number
  stacks_with_promo: boolean
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
  discount_type: "percent",
  discount_value: 10,
  stacks_with_promo: false,
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
            discount_type: tier.discount_type,
            discount_value: Number(tier.discount_value),
            stacks_with_promo: tier.stacks_with_promo,
            status: tier.status,
          }
        : EMPTY,
    )
  }, [open, tier, form])

  const discountType = form.watch("discount_type")

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

                <div className="grid gap-4 sm:grid-cols-2">
                  <FormSelect
                    control={form.control}
                    name="discount_type"
                    label={t("promo.discount_type")}
                    options={[
                      { label: t("clinic.discount_type.percent"), value: "percent" },
                      { label: t("clinic.discount_type.fixed"), value: "fixed" },
                    ]}
                  />
                  <FormInput
                    control={form.control}
                    name="discount_value"
                    label={t("membership.discount_value")}
                    type="number"
                    // step bawaan input number adalah 1, jadi 12,5 ditolak
                    // peramban sebelum sempat sampai ke server.
                    step={0.01}
                    min={0.01}
                    required
                    description={
                      discountType === "percent"
                        ? t("promo.percent_hint")
                        : t("promo.fixed_hint")
                    }
                    inputClassName="tabular-nums"
                  />
                </div>

                <FormSwitch
                  control={form.control}
                  name="stacks_with_promo"
                  label={t("membership.stacks_with_promo")}
                  description={t("membership.stacks_with_promo_hint")}
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

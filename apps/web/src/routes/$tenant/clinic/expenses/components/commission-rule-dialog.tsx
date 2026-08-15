import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
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
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export interface CommissionRule {
  id: number
  name: string
  therapist_id?: number | null
  therapist_name?: string | null
  type: string
  type_label?: string | null
  amount: string | number
  percent: string | number
  min_revenue: string | number
  is_active: boolean
}

const schema = z.object({
  name: z.string().min(1),
  // "" berarti berlaku untuk semua terapis.
  therapist_id: z.string().optional(),
  type: z.string().min(1),
  amount: z.coerce.number().gte(0),
  percent: z.coerce.number().gte(0).lte(100),
  min_revenue: z.coerce.number().gte(0),
})

type Values = z.infer<typeof schema>

const EMPTY: Values = {
  name: "",
  therapist_id: "",
  type: "per_patient",
  amount: 5000,
  percent: 0,
  min_revenue: 0,
}

interface CommissionRuleDialogProps {
  tenant: string
  rule?: CommissionRule
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function CommissionRuleDialog({
  tenant,
  rule,
  open,
  onOpenChange,
}: CommissionRuleDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = rule !== undefined

  const form = useForm(schema, { defaultValues: EMPTY })
  const type = form.watch("type")

  const staff = useQuery({
    queryKey: ["staff", tenant, "therapists"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string; clinic_role: string }[] }>(
        `/${tenant}/clinic/staff`,
        { per_page: 100 },
      ),
    enabled: open,
  })

  useEffect(() => {
    if (!open) return

    form.reset(
      rule
        ? {
            name: rule.name,
            therapist_id: rule.therapist_id ? String(rule.therapist_id) : "",
            type: rule.type,
            amount: Number(rule.amount),
            percent: Number(rule.percent),
            min_revenue: Number(rule.min_revenue),
          }
        : EMPTY,
    )
  }, [open, rule, form])

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      const payload = {
        ...values,
        therapist_id: values.therapist_id ? Number(values.therapist_id) : null,
      }

      return isEdit
        ? apiPut(`/${tenant}/clinic/commission-rules/${rule.id}`, payload)
        : apiPost(`/${tenant}/clinic/commission-rules`, payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? t("commission.updated") : t("commission.created"))
      qc.invalidateQueries({ queryKey: ["commission-rules"] })
      qc.invalidateQueries({ queryKey: ["commission-preview"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const isPercent = type === "revenue_percent"

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {isEdit ? t("commission.edit") : t("commission.add")}
          </DialogTitle>
          <DialogDescription>{t("commission.tier_note")}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <FormInput
              control={form.control}
              name="name"
              label={t("commission.name")}
              required
            />

            <FormSelect
              control={form.control}
              name="therapist_id"
              label={t("commission.therapist_scope")}
              options={[
                { label: t("commission.all_therapists"), value: "" },
                ...(staff.data?.data ?? [])
                  .filter((member) =>
                    ["therapist", "doctor"].includes(member.clinic_role),
                  )
                  .map((member) => ({
                    label: member.name,
                    value: String(member.id),
                  })),
              ]}
            />

            <FormSelect
              control={form.control}
              name="type"
              label={t("commission.type_label")}
              options={[
                { label: t("commission.type.per_patient"), value: "per_patient" },
                { label: t("commission.type.per_new_patient"), value: "per_new_patient" },
                { label: t("commission.type.revenue_percent"), value: "revenue_percent" },
              ]}
            />

            {/* Hanya field yang relevan dengan jenisnya yang ditampilkan —
                kolom nominal pada aturan persentase cuma jadi jebakan isi. */}
            {isPercent ? (
              <div className="grid gap-4 sm:grid-cols-2">
                <FormInput
                  control={form.control}
                  name="percent"
                  label={t("commission.percent")}
                  type="number"
                  min={0}
                  max={100}
                  required
                  inputClassName="tabular-nums"
                />
                <FormInput
                  control={form.control}
                  name="min_revenue"
                  label={t("commission.min_revenue")}
                  type="number"
                  min={0}
                  inputClassName="tabular-nums"
                />
              </div>
            ) : (
              <FormInput
                control={form.control}
                name="amount"
                label={t("commission.amount")}
                type="number"
                min={0}
                required
                inputClassName="tabular-nums"
              />
            )}

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

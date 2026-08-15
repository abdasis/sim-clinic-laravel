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
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

/** Sama persis dengan enum di backend; label-nya datang dari i18n. */
export const EXPENSE_CATEGORIES = [
  "operational",
  "salary",
  "incentive",
  "purchase",
  "rent",
  "utility",
  "marketing",
  "other",
] as const

const schema = z.object({
  spent_at: z.string().min(1),
  category: z.string().min(1),
  description: z.string().min(1),
  amount: z.coerce.number().gt(0),
  note: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface ExpenseFormValues {
  id: number
  spent_at: string
  category: string
  description: string
  amount: string | number
  note?: string | null
}

interface ExpenseFormDialogProps {
  tenant: string
  expense?: ExpenseFormValues
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Nilai awal untuk pencatatan dari pratinjau fee. */
  preset?: Partial<Values>
}

function today() {
  return new Date().toISOString().slice(0, 10)
}

export function ExpenseFormDialog({
  tenant,
  expense,
  open,
  onOpenChange,
  preset,
}: ExpenseFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = expense !== undefined

  const empty: Values = {
    spent_at: today(),
    category: "operational",
    description: "",
    amount: 0,
    note: "",
  }

  const form = useForm(schema, { defaultValues: empty })

  useEffect(() => {
    if (!open) return

    form.reset(
      expense
        ? {
            spent_at: expense.spent_at,
            category: expense.category,
            description: expense.description,
            amount: Number(expense.amount),
            note: expense.note ?? "",
          }
        : { ...empty, ...preset },
    )
    // `empty` dibentuk ulang tiap render; yang menentukan isi form hanya
    // dialog terbuka, data yang diubah, dan preset-nya.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, expense, preset, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      isEdit
        ? apiPut(`/${tenant}/clinic/expenses/${expense.id}`, values)
        : apiPost(`/${tenant}/clinic/expenses`, values),
    onSuccess: () => {
      toast.success(isEdit ? t("expense.updated") : t("expense.created"))
      qc.invalidateQueries({ queryKey: ["expenses"] })
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
          <DialogTitle>
            {isEdit ? t("expense.edit") : t("expense.add")}
          </DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <FormDatePicker
                control={form.control}
                name="spent_at"
                label={t("expense.spent_at")}
                required
              />
              <FormSelect
                control={form.control}
                name="category"
                label={t("expense.category_label")}
                options={EXPENSE_CATEGORIES.map((value) => ({
                  label: t(`expense.category.${value}`),
                  value,
                }))}
              />
            </div>

            <FormInput
              control={form.control}
              name="description"
              label={t("expense.description")}
              required
            />

            <FormInput
              control={form.control}
              name="amount"
              label={t("expense.amount")}
              type="number"
              min={1}
              required
              inputClassName="tabular-nums"
            />

            <FormTextarea
              control={form.control}
              name="note"
              label={t("expense.note")}
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

import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"

import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { Alert, AlertDescription } from "#/components/ui/alert.tsx"
import { Form } from "#/components/ui/form.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  method: z.string().min(1),
  amount: z.coerce.number().gt(0),
  paid_at: z.string().min(1),
})

type Values = z.infer<typeof schema>

interface PaymentFormProps {
  tenant: string
  transactionId: string
  /** Sisa tagihan; dipakai sebagai nilai awal supaya pelunasan sekali klik. */
  outstanding: number
  disabled?: boolean
  overpaid?: boolean
}

/**
 * Catat satu cicilan. Pasien kerap membayar bertahap, jadi nominalnya bebas
 * diubah — bukan hanya pelunasan penuh.
 */
export function PaymentForm({
  tenant,
  transactionId,
  outstanding,
  disabled,
  overpaid,
}: PaymentFormProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const form = useForm(schema, {
    defaultValues: {
      method: "cash",
      amount: outstanding > 0 ? outstanding : 0,
      paid_at: localNow(),
    },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost(`/${tenant}/clinic/transactions/${transactionId}/payments`, values),
    onSuccess: () => {
      toast.success(t("pos.paid"))
      qc.invalidateQueries({ queryKey: ["transaction", tenant, transactionId] })
      qc.invalidateQueries({ queryKey: ["transactions"] })
      form.reset({ method: "cash", amount: 0, paid_at: localNow() })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)

      if (!err.errors) {
        form.setError("amount", { type: "server", message: err.message })
      }

      toast.error(err.message)
    },
  })

  const methods = [
    { value: "cash", label: t("clinic.payment_method.cash") },
    { value: "transfer", label: t("clinic.payment_method.transfer") },
    { value: "qris", label: t("clinic.payment_method.qris") },
    { value: "debit", label: t("clinic.payment_method.debit") },
  ]

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        className="space-y-4"
      >
        {overpaid ? (
          <Alert variant="destructive">
            <AlertDescription>{t("pos.overpaid_warning")}</AlertDescription>
          </Alert>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-3">
          <FormSelect
            control={form.control}
            name="method"
            label={t("pos.method")}
            placeholder={t("pos.method")}
            options={methods}
            disabled={disabled}
          />
          <FormInput
            control={form.control}
            name="amount"
            label={t("pos.amount")}
            type="number"
            disabled={disabled}
          />
          <FormInput
            control={form.control}
            name="paid_at"
            label={t("pos.paid_at")}
            type="datetime-local"
            disabled={disabled}
          />
        </div>

        <FormSubmit loading={mutation.isPending} disabled={disabled}>
          {t("pos.record_payment")}
        </FormSubmit>
      </form>
    </Form>
  )
}

/** `datetime-local` menolak offset zona waktu, jadi dipotong ke menit. */
function localNow(): string {
  const now = new Date()

  now.setMinutes(now.getMinutes() - now.getTimezoneOffset())

  return now.toISOString().slice(0, 16)
}

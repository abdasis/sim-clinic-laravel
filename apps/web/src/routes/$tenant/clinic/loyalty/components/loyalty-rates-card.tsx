import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatCurrency } from "#/lib/format.ts"

const schema = z.object({
  earn_rate: z.coerce.number().gt(0),
  redeem_rate: z.coerce.number().gt(0),
  min_redeem: z.coerce.number().int().min(1),
})

type Values = z.infer<typeof schema>

const FALLBACK: Values = { earn_rate: 10_000, redeem_rate: 1_000, min_redeem: 10 }

/**
 * Tarif poin klinik ini.
 *
 * Angkanya jarang diubah tapi berat akibatnya, jadi kartunya menolak jadi tiga
 * kolom kosong: contoh hitungan di bawah ikut berubah saat angkanya diketik,
 * supaya yang menyetel melihat akibat keputusannya sebelum menekan simpan —
 * bukan setelah pasien pertama menukar poinnya di kasir.
 */
export function LoyaltyRatesCard({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const setting = useQuery({
    queryKey: ["loyalty-settings", tenant],
    queryFn: () => apiGet<{ data: Values }>(`/${tenant}/clinic/loyalty-settings`),
  })

  const form = useForm(schema, { defaultValues: FALLBACK })

  useEffect(() => {
    if (!setting.data) return

    form.reset({
      earn_rate: Number(setting.data.data.earn_rate),
      redeem_rate: Number(setting.data.data.redeem_rate),
      min_redeem: Number(setting.data.data.min_redeem),
    })
  }, [setting.data, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut(`/${tenant}/clinic/loyalty-settings`, values),
    onSuccess: () => {
      toast.success(t("loyalty.setting_saved"))
      qc.invalidateQueries({ queryKey: ["loyalty-settings", tenant] })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  // Contoh hidup: yang dilihat penyetel adalah akibat angkanya, bukan angkanya.
  const earn = Number(form.watch("earn_rate")) || 0
  const redeem = Number(form.watch("redeem_rate")) || 0
  const spend = earn > 0 ? earn * 10 : 0
  const points = earn > 0 ? Math.floor(spend / earn) : 0
  const cashback = earn > 0 ? (redeem / earn) * 100 : 0

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("loyalty.title")}</CardTitle>
        <CardDescription>{t("loyalty.section_desc")}</CardDescription>
      </CardHeader>

      <CardContent>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <div className="grid gap-4 sm:grid-cols-3">
              <FormInput
                control={form.control}
                name="earn_rate"
                label={t("loyalty.earn_rate")}
                type="number"
                min={1}
                step={100}
                required
                description={t("loyalty.earn_rate_hint")}
                inputClassName="tabular-nums"
              />
              <FormInput
                control={form.control}
                name="redeem_rate"
                label={t("loyalty.redeem_rate")}
                type="number"
                min={1}
                step={100}
                required
                description={t("loyalty.redeem_rate_hint")}
                inputClassName="tabular-nums"
              />
              <FormInput
                control={form.control}
                name="min_redeem"
                label={t("loyalty.min_redeem")}
                type="number"
                min={1}
                step={1}
                required
                description={t("loyalty.min_redeem_hint")}
                inputClassName="tabular-nums"
              />
            </div>

            {earn > 0 && redeem > 0 ? (
              <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2.5 text-xs">
                <p className="font-medium text-foreground">
                  {t("loyalty.preview")}
                </p>
                <p className="mt-1 text-muted-foreground">
                  {t("loyalty.preview_text")
                    .replace(":spend", formatCurrency(spend))
                    .replaceAll(":points", String(points))
                    .replace(":value", formatCurrency(points * redeem))}
                </p>
                <p className="mt-1 text-muted-foreground tabular-nums">
                  {t("loyalty.rate_cashback").replace(
                    ":percent",
                    cashback.toFixed(cashback < 1 ? 2 : 1),
                  )}
                </p>
              </div>
            ) : null}

            <div className="flex justify-end">
              <FormSubmit loading={mutation.isPending}>
                {t("general.save")}
              </FormSubmit>
            </div>
          </form>
        </Form>
      </CardContent>
    </Card>
  )
}

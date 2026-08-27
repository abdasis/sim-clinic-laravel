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
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  PromoTargetPicker,
  type PromoTarget,
} from "./promo-target-picker.tsx"

const schema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
  discount_type: z.string().min(1),
  discount_value: z.coerce.number().gt(0),
  starts_at: z.string().min(1),
  ends_at: z.string().min(1),
  status: z.string().optional(),
})

type Values = z.infer<typeof schema>

export interface PromoFormValues {
  id: number
  name: string
  description?: string | null
  discount_type: string
  discount_value: string | number
  starts_at: string
  ends_at: string
  status: string
  targets?: PromoTarget[]
}

interface PromoFormDialogProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  promo?: PromoFormValues
  open: boolean
  onOpenChange: (open: boolean) => void
}

function today() {
  return new Date().toISOString().slice(0, 10)
}

const EMPTY: Values = {
  name: "",
  description: "",
  discount_type: "percent",
  discount_value: 10,
  starts_at: today(),
  ends_at: today(),
  status: "active",
}

/**
 * Satu dialog untuk tambah dan ubah promo. Sasaran promo tidak ikut skema zod
 * karena bentuknya daftar objek yang dikelola pemilihnya sendiri; validasinya
 * ditegakkan server dan galatnya dipantulkan ke pemilih.
 */
export function PromoFormDialog({
  tenant,
  promo,
  open,
  onOpenChange,
}: PromoFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = promo !== undefined

  const [targets, setTargets] = useState<PromoTarget[]>([])
  const [targetError, setTargetError] = useState<string | undefined>()

  const form = useForm(schema, { defaultValues: EMPTY })

  useEffect(() => {
    if (!open) return

    setTargetError(undefined)
    setTargets(promo?.targets ?? [])
    form.reset(
      promo
        ? {
            name: promo.name,
            description: promo.description ?? "",
            discount_type: promo.discount_type,
            discount_value: Number(promo.discount_value),
            starts_at: promo.starts_at,
            ends_at: promo.ends_at,
            status: promo.status,
          }
        : EMPTY,
    )
  }, [open, promo, form])

  // Keterangan besaran potongan ikut jenisnya: 70,5 berarti persen di
  // satu pilihan dan rupiah di pilihan lainnya.
  const discountType = form.watch("discount_type")

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      const payload = { ...values, targets: targets.map(({ type, id }) => ({ type, id })) }

      return isEdit
        ? apiPut(`/${tenant}/clinic/promos/${promo.id}`, payload)
        : apiPost(`/${tenant}/clinic/promos`, payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? t("promo.updated") : t("promo.created"))
      qc.invalidateQueries({ queryKey: ["promos"] })
      // Katalog kasir ikut disegarkan supaya harga promonya langsung terasa.
      qc.invalidateQueries({ queryKey: ["services"] })
      qc.invalidateQueries({ queryKey: ["products"] })
      onOpenChange(false)
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      // Galat sasaran datang sebagai `targets` atau `targets.0.id`; pemiliknya
      // di luar react-hook-form, jadi pesannya dipasang manual.
      setTargetError(
        Object.entries(err.errors ?? {}).find(([key]) =>
          key.startsWith("targets"),
        )?.[1]?.[0],
      )
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] gap-0 overflow-hidden p-0 sm:max-w-lg">
        <DialogHeader className="border-b border-border/50 p-4">
          <DialogTitle>{isEdit ? t("promo.edit") : t("promo.add")}</DialogTitle>
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
                  label={t("promo.name")}
                  required
                />

                <FormTextarea
                  control={form.control}
                  name="description"
                  label={t("promo.description")}
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
                    label={t("promo.discount_value")}
                    type="number"
                    // step bawaan input number adalah 1, jadi 70,5 ditolak
                    // peramban sebelum sempat sampai ke server — padahal
                    // kolomnya sudah desimal sejak awal.
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

                <div className="grid gap-4 sm:grid-cols-2">
                  <FormDatePicker
                    control={form.control}
                    name="starts_at"
                    label={t("promo.starts_at")}
                    required
                  />
                  <FormDatePicker
                    control={form.control}
                    name="ends_at"
                    label={t("promo.ends_at")}
                    required
                  />
                </div>

                <FormSelect
                  control={form.control}
                  name="status"
                  label={t("promo.status")}
                  options={[
                    { label: t("clinic.promo_status.active"), value: "active" },
                    { label: t("clinic.promo_status.inactive"), value: "inactive" },
                  ]}
                />

                <PromoTargetPicker
                  tenant={tenant}
                  value={targets}
                  onChange={(next) => {
                    setTargets(next)
                    if (next.length > 0) setTargetError(undefined)
                  }}
                  error={targetError}
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

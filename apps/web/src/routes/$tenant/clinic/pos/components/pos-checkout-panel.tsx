import { Link } from "@tanstack/react-router"
import { z } from "zod"
import type { UseFormReturn } from "react-hook-form"

import { Form } from "#/components/ui/form.tsx"
import { FormCombobox } from "#/components/forms/form-combobox.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { PaymentPanel, type PaymentData } from "./payment-panel.tsx"
import { PosCart } from "./pos-cart.tsx"
import type { LineItem } from "../hooks/use-pos-cart.ts"

export const patientSchema = z.object({
  patient_id: z.string().min(1),
})

export type PatientFormValues = z.output<typeof patientSchema>

export interface CreatedTransaction {
  id: number
  invoice_number: string
}

interface PosCheckoutPanelProps {
  tenant: string
  form: UseFormReturn<PatientFormValues>
  patientOptions: { label: string; value: string }[]
  created: CreatedTransaction | null
  items: LineItem[]
  total: number
  onStep: (key: string, delta: number) => void
  onRemove: (key: string) => void
  onClear: () => void
  onPaymentChange: (payment: PaymentData) => void
  /** Dipakai halaman untuk menggulir ke field pasien saat simpan ditolak. */
  patientFieldRef?: React.Ref<HTMLDivElement>
  /** Wadah portal daftar pasien; diisi saat panel berada di dalam drawer. */
  popupContainer?: HTMLElement | null
}

/**
 * Isi kasir sisi kanan: pasien, keranjang, dan pembayaran. Dipisah dari
 * halamannya karena di layar sempit panel ini pindah ke drawer — bentuknya
 * sama, hanya wadahnya yang berbeda, jadi cukup satu sumber markup.
 */
export function PosCheckoutPanel({
  tenant,
  form,
  patientOptions,
  created,
  items,
  total,
  onStep,
  onRemove,
  onClear,
  onPaymentChange,
  patientFieldRef,
  popupContainer,
}: PosCheckoutPanelProps) {
  const { t } = useTrans()

  return (
    <div className="space-y-4">
      {created ? (
        <div className="rounded-md border border-primary/40 bg-primary/5 p-3 text-sm">
          <span className="font-medium">{created.invoice_number}</span> —{" "}
          <Link
            to="/$tenant/clinic/pos/invoices/$id"
            params={{ tenant, id: String(created.id) }}
            className="text-primary underline underline-offset-4 transition-colors hover:text-primary/80"
          >
            {t("invoice.title")}
          </Link>
        </div>
      ) : null}

      <Form {...form}>
        <div ref={patientFieldRef}>
          <FormCombobox
            control={form.control}
            name="patient_id"
            label={t("pos.patient")}
            placeholder={t("general.search")}
            emptyLabel={t("general.no_data")}
            options={patientOptions}
            required
            container={popupContainer}
          />
        </div>
      </Form>

      <PosCart
        items={items}
        total={total}
        onStep={onStep}
        onRemove={onRemove}
        onClear={onClear}
      />

      <PaymentPanel total={total} onChange={onPaymentChange} />
    </div>
  )
}

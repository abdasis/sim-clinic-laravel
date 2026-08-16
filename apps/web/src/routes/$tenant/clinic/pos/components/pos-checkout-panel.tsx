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
  // Opsional: dipakai menghitung fee terapis, bukan syarat transaksi.
  therapist_id: z.string().optional(),
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
  therapistOptions: { label: string; value: string }[]
  created: CreatedTransaction | null
  items: LineItem[]
  total: number
  onStep: (key: string, delta: number) => void
  onRemove: (key: string) => void
  onClear: () => void
  onPaymentChange: (payment: PaymentData) => void
  /** Dipakai halaman untuk menggulir ke field pasien saat simpan ditolak. */
  patientFieldRef?: React.Ref<HTMLDivElement>
  /**
   * Wadah portal daftar pasien; diisi saat panel berada di dalam drawer.
   *
   * Bedakan dua nilai kosongnya: `undefined` berarti "tidak perlu wadah
   * khusus, portal ke body", sedangkan `null` berarti "wadahnya belum
   * terpasang" dan Base UI sengaja menahan portalnya sampai ada. Mengirim
   * `null` di layar lebar membuat daftarnya tidak pernah muncul sama sekali.
   */
  popupContainer?: HTMLElement | null
  /**
   * Keadaan pengambilan daftar pilihan. Tanpa ini, daftar pasien yang gagal
   * dimuat tampak sama dengan klinik yang memang belum punya pasien — dan
   * kasir menyimpulkan fieldnya rusak.
   */
  optionsLoading?: boolean
  optionsError?: boolean
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
  therapistOptions,
  created,
  items,
  total,
  onStep,
  onRemove,
  onClear,
  onPaymentChange,
  patientFieldRef,
  popupContainer,
  optionsLoading,
  optionsError,
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
            loading={optionsLoading}
            error={optionsError}
          />

          {/* Terapis menentukan fee bulanan, jadi diisi di kasir saat
              transaksinya dibuat — bukan direkap ulang dari ingatan. */}
          <div className="mt-4">
            <FormCombobox
              control={form.control}
              name="therapist_id"
              label={t("commission.therapist")}
              placeholder={t("general.search")}
              emptyLabel={t("general.no_data")}
              options={therapistOptions}
              container={popupContainer}
              loading={optionsLoading}
              error={optionsError}
            />
          </div>
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

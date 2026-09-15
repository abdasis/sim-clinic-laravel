import { z } from "zod"
import type { UseFormReturn } from "react-hook-form"

import { Badge } from "#/components/ui/badge.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormCombobox } from "#/components/forms/form-combobox.tsx"
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import {
  DiscountField,
  discountAmount,
  type DiscountState,
} from "./discount-field.tsx"
import {
  memberDiscountAmount,
  type MembershipInfo,
} from "./member-discount.ts"
import { PaymentPanel, type PaymentData } from "./payment-panel.tsx"
import { PerformerPicker, type StaffOption } from "./performer-picker.tsx"
import { PosCart } from "./pos-cart.tsx"
import type { LineItem } from "../hooks/use-pos-cart.ts"

export const patientSchema = z.object({
  patient_id: z.string().min(1),
  // Opsional juga: penjualan produk di etalase tidak berasal dari kunjungan
  // mana pun. Diisi saat tagihan ini memang menagih kunjungan yang selesai,
  // sehingga transaksi dan rekam medisnya tersambung lewat booking yang sama.
  booking_id: z.string().optional(),
  // Kosong berarti hari ini. Diisi saat admin mencatat penjualan yang
  // terlewat; server menolak tanggal di masa depan.
  issued_at: z.string().optional(),
})

export type PatientFormValues = z.output<typeof patientSchema>

export interface CreatedTransaction {
  id: number
  invoice_number: string
}

interface PosCheckoutPanelProps {
  form: UseFormReturn<PatientFormValues>
  patientOptions: { label: string; value: string }[]
  /** Kunjungan selesai milik pasien terpilih; kosong sebelum pasien dipilih. */
  bookingOptions: { label: string; value: string }[]
  /** Staf yang boleh dicatat sebagai pelaksana maupun penawar. */
  staff: StaffOption[]
  staffLoading?: boolean
  performerIds: number[]
  onPerformersChange: (next: number[]) => void
  onOfferedBy: (key: string, userId: number | null) => void
  bookingsLoading?: boolean
  /** Pasien belum dipilih, jadi daftar kunjungan memang belum bisa diisi. */
  bookingsNeedPatient?: boolean
  items: LineItem[]
  /** Total keranjang sebelum potongan nota. */
  total: number
  /** Keanggotaan pasien terpilih, atau null bila bukan member. */
  membership?: MembershipInfo | null
  discount: DiscountState
  onDiscountChange: (next: DiscountState) => void
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
  form,
  patientOptions,
  bookingOptions,
  staff,
  staffLoading,
  performerIds,
  onPerformersChange,
  onOfferedBy,
  bookingsLoading,
  bookingsNeedPatient,
  items,
  total,
  membership = null,
  discount,
  onDiscountChange,
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
  // Batas atas tanggal: nota bertanggal besok tidak punya arti, dan server
  // menolaknya juga.
  const today = new Date().toISOString().slice(0, 10)

  // Potongan member dihitung lebih dulu dan tidak bisa ditawar kasir — itu
  // manfaat yang sudah dibayar pasien saat mendaftar. Potongan manual di
  // bawah menyusul di atas sisanya, persis urutan yang dipakai server, supaya
  // angka yang dilihat kasir sebelum menekan bayar sama dengan yang tersimpan.
  const memberAmount = memberDiscountAmount(items, membership)
  const afterMember = Math.max(0, total - memberAmount)

  return (
    <div className="space-y-4">
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

          {/* Ditunjukkan begitu pasiennya dipilih, sebelum kasir sempat
              menghitung sendiri: manfaat member tidak boleh baru ketahuan
              saat nota sudah tercetak. */}
          {membership ? (
            <div className="mt-2 flex items-center justify-between gap-2 rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs">
              <div className="flex min-w-0 items-center gap-1.5">
                <Badge variant="secondary" className="shrink-0 font-normal">
                  {membership.name}
                </Badge>
                <span className="truncate text-muted-foreground">
                  {t("pos.member_active")}
                </span>
              </div>
              {memberAmount > 0 ? (
                <span className="shrink-0 font-medium tabular-nums text-primary">
                  −{formatCurrency(memberAmount)}
                </span>
              ) : null}
            </div>
          ) : null}

          <div className="mt-4">
            <FormDatePicker
              control={form.control}
              name="issued_at"
              label={t("pos.transaction_date")}
              max={today}
              description={t("pos.transaction_date_hint")}
            />
          </div>

          {/* Tautan ke kunjungan: inilah yang menyambungkan tagihan dengan
              rekam medis, karena keduanya menunjuk booking yang sama. */}
          <div className="mt-4">
            <FormCombobox
              control={form.control}
              name="booking_id"
              label={t("pos.booking_optional")}
              placeholder={t("general.search")}
              options={bookingOptions}
              container={popupContainer}
              loading={bookingsLoading}
              emptyLabel={
                bookingsNeedPatient
                  ? t("pos.booking_pick_patient")
                  : t("pos.booking_none")
              }
              description={t("pos.booking_hint")}
            />
          </div>
        </div>
      </Form>

      <PerformerPicker
        staff={staff}
        loading={staffLoading}
        value={performerIds}
        onChange={onPerformersChange}
      />

      <PosCart
        items={items}
        total={total}
        onStep={onStep}
        onRemove={onRemove}
        onClear={onClear}
        staff={staff}
        onOfferedBy={onOfferedBy}
      />

      <DiscountField
        value={discount}
        onChange={onDiscountChange}
        total={afterMember}
      />

      {/* Yang dibayar adalah jumlah setelah potongan member dan potongan
          manual: kembalian dan sisa tagihan harus dihitung dari angka yang
          sama dengan yang ditagih. */}
      <PaymentPanel
        total={Math.max(0, afterMember - discountAmount(discount, afterMember))}
        onChange={onPaymentChange}
      />
    </div>
  )
}

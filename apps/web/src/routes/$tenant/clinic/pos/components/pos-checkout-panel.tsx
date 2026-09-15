import { z } from "zod"
import type { UseFormReturn } from "react-hook-form"

import { Form } from "#/components/ui/form.tsx"
import { FormCombobox } from "#/components/forms/form-combobox.tsx"
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import {
  DiscountField,
  discountAmount,
  type DiscountState,
} from "./discount-field.tsx"
import {
  DEFAULT_RATES,
  capToBill,
  loyaltyPointsPreview,
  redeemValue,
  type LoyaltyRates,
} from "./loyalty-points.ts"
import { PaymentPanel, type PaymentData } from "./payment-panel.tsx"
import { PerformerPicker, type StaffOption } from "./performer-picker.tsx"
import { PosCart } from "./pos-cart.tsx"
import { RedeemPointsField } from "./redeem-points-field.tsx"
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
  /** Saldo poin pasien terpilih; null berarti belum ada pasien dipilih. */
  loyaltyPoints?: number | null
  /** Hanya member yang mengumpulkan poin dari transaksi ini. */
  isMember?: boolean
  discount: DiscountState
  onDiscountChange: (next: DiscountState) => void
  /** Poin yang hendak ditukar; string supaya kolomnya boleh kosong. */
  redeemPoints?: string
  onRedeemPointsChange?: (next: string) => void
  /** Tarif poin klinik ini; bawaan dipakai selama jawabannya belum tiba. */
  loyaltyRates?: LoyaltyRates
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
  loyaltyPoints = null,
  isMember = false,
  discount,
  onDiscountChange,
  redeemPoints = "",
  onRedeemPointsChange,
  loyaltyRates = DEFAULT_RATES,
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

  // Urutannya mengikuti server: potongan nota lebih dulu, penukaran poin di
  // atas sisanya. Kebalikannya membuat angka di layar kasir berbeda dengan
  // yang tersimpan, dan selisihnya baru ketahuan setelah nota tercetak.
  const afterDiscount = Math.max(0, total - discountAmount(discount, total))
  const redeemed = capToBill(
    Math.min(Number(redeemPoints) || 0, loyaltyPoints ?? 0),
    afterDiscount,
    loyaltyRates,
  )
  const payableTotal = Math.max(
    0,
    afterDiscount - redeemValue(redeemed, loyaltyRates),
  )
  const pointsPreview = loyaltyPointsPreview(payableTotal, loyaltyRates)

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

          {/* Perkiraan poin hanya dijanjikan kepada member. Menampilkannya
              untuk pelanggan biasa berarti kasir menyebut angka yang tidak
              akan pernah masuk — dan pasien menanyakannya di kunjungan
              berikutnya. */}
          {loyaltyPoints !== null ? (
            <div className="mt-2 flex items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2 text-xs text-muted-foreground">
              {isMember ? (
                <>
                  <span>
                    {t("pos.loyalty_balance")}{" "}
                    <span className="font-medium tabular-nums text-foreground">
                      {loyaltyPoints}
                    </span>
                  </span>
                  {pointsPreview > 0 ? (
                    <span className="shrink-0 font-medium tabular-nums text-primary">
                      +{pointsPreview} {t("pos.loyalty_points_unit")}
                    </span>
                  ) : null}
                </>
              ) : (
                <span>{t("patient.not_member_hint")}</span>
              )}
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
        total={total}
      />

      {/* Muncul hanya saat pasiennya punya poin yang benar-benar terpakai —
          komponennya sendiri yang memutuskan, karena batasnya ikut berubah
          tiap kali keranjang atau potongannya berubah. */}
      {onRedeemPointsChange ? (
        <RedeemPointsField
          value={redeemPoints}
          onChange={onRedeemPointsChange}
          balance={loyaltyPoints ?? 0}
          payable={afterDiscount}
          rates={loyaltyRates}
        />
      ) : null}

      <PaymentPanel total={payableTotal} onChange={onPaymentChange} />
    </div>
  )
}

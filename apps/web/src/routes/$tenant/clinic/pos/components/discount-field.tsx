import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"

export type DiscountKind = "none" | "percent" | "fixed"

export interface DiscountState {
  kind: DiscountKind
  /** Apa yang diketik kasir: 70,5 untuk persen, atau nilai rupiah. */
  value: string
}

export const EMPTY_DISCOUNT: DiscountState = { kind: "none", value: "" }

/**
 * Hitung potongan dalam rupiah dari isian kasir.
 *
 * Berhenti di gratis, tidak pernah melahirkan nota bernilai minus — sama
 * seperti perlakuan promo di sisi server, supaya angka di layar kasir dan
 * angka yang tersimpan tidak pernah berbeda.
 */
export function discountAmount(discount: DiscountState, total: number): number {
  const value = Number(discount.value.replace(",", "."))

  if (discount.kind === "none" || !Number.isFinite(value) || value <= 0) {
    return 0
  }

  const raw = discount.kind === "percent" ? (total * value) / 100 : value

  return Math.min(Math.round(raw * 100) / 100, total)
}

interface DiscountFieldProps {
  value: DiscountState
  onChange: (next: DiscountState) => void
  /** Total keranjang sebelum potongan, untuk menampilkan hasilnya. */
  total: number
}

/**
 * Potongan di tingkat nota, di luar promo yang menempel per barang.
 *
 * Promo menjawab "layanan ini sedang diskon"; yang belum terjawab adalah
 * potongan yang diputuskan di meja kasir. Selama ini kasir menyiasatinya
 * dengan mengubah harga satuan — dan harga asli layanan ikut hilang dari
 * nota maupun laporan.
 */
export function DiscountField({ value, onChange, total }: DiscountFieldProps) {
  const { t } = useTrans()
  const amount = discountAmount(value, total)

  return (
    <div className="space-y-2 rounded-md border border-border/60 p-3">
      <Label className="text-xs">{t("pos.discount")}</Label>

      <div className="flex gap-2">
        <NativeSelect
          value={value.kind}
          aria-label={t("pos.discount_type")}
          onChange={(event) =>
            onChange({
              kind: event.target.value as DiscountKind,
              // Nilainya dikosongkan saat jenisnya berganti: 10 sebagai
              // persen dan 10 sebagai rupiah adalah dua hal yang jauh
              // berbeda, dan membawanya menyeberang mengundang salah tekan.
              value: "",
            })
          }
          className="h-8 w-36 text-xs"
        >
          <NativeSelectOption value="none">
            {t("pos.discount_none")}
          </NativeSelectOption>
          <NativeSelectOption value="percent">
            {t("clinic.discount_type.percent")}
          </NativeSelectOption>
          <NativeSelectOption value="fixed">
            {t("clinic.discount_type.fixed")}
          </NativeSelectOption>
        </NativeSelect>

        {value.kind === "none" ? null : (
          <Input
            type="number"
            inputMode="decimal"
            // step bawaan input number adalah 1, jadi 70,5 ditolak peramban
            // sebelum sempat dihitung.
            step={0.01}
            min={0}
            max={value.kind === "percent" ? 100 : undefined}
            value={value.value}
            onChange={(event) =>
              onChange({ ...value, value: event.target.value })
            }
            aria-label={t("pos.discount_value")}
            className="h-8 flex-1 text-xs tabular-nums"
          />
        )}
      </div>

      {amount > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t("pos.discount")}{" "}
          <span className="font-medium text-foreground tabular-nums">
            −{formatCurrency(amount)}
          </span>
        </p>
      ) : value.kind === "percent" ? (
        <p className="text-xs text-muted-foreground">
          {t("pos.discount_hint_percent")}
        </p>
      ) : null}
    </div>
  )
}

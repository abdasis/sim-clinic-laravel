import { useEffect, useState } from "react"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import {
  PAYMENT_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatAmount, formatCurrency } from "#/lib/format.ts"

export interface PaymentData {
  method: string
  amount: number
  covers: boolean
}

interface PaymentPanelProps {
  total: number
  onChange: (payment: PaymentData) => void
}

const METHODS = ["cash", "transfer", "qris", "debit"] as const

export function PaymentPanel({ total, onChange }: PaymentPanelProps) {
  const { t } = useTrans()
  const [method, setMethod] = useState<string>("cash")
  // Disimpan sebagai digit mentah, bukan number. Input number yang bernilai 0
  // menyisakan angka nol di depan begitu kasir mengetik (0 -> 05000000), dan
  // nominal rupiah lebih mudah dibaca kalau ribuannya dipisah.
  const [rawAmount, setRawAmount] = useState("")
  const amount = Number(rawAmount || 0)

  const covers = amount >= total && total > 0
  const outstanding = Math.max(0, total - amount)

  // Cerminkan tiga keadaan yang sama dengan backend agar kasir tahu
  // transaksi akan tercatat sebagai cicilan, bukan lunas.
  const paymentStatus =
    amount <= 0 ? "unpaid" : covers ? "paid" : "partially_paid" 

  useEffect(() => {
    onChange({ method, amount, covers })
  }, [method, amount, covers, onChange])

  return (
    <div className="space-y-3">
      <h2 className="text-sm font-semibold">{t("pos.payment")}</h2>

      <div className="space-y-1.5">
        <Label>{t("pos.method")}</Label>
        <NativeSelect
          className="w-full"
          value={method}
          onChange={(e) => setMethod(e.target.value)}
        >
          {METHODS.map((m) => (
            <NativeSelectOption key={m} value={m}>
              {t(`clinic.payment_method.${m}`)}
            </NativeSelectOption>
          ))}
        </NativeSelect>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="pos-amount">{t("pos.amount")}</Label>
        <Input
          id="pos-amount"
          type="text"
          inputMode="numeric"
          autoComplete="off"
          className="tabular-nums"
          placeholder="0"
          value={rawAmount === "" ? "" : formatAmount(amount)}
          onChange={(event) =>
            // Buang apa pun selain angka, lalu buang nol di depan supaya
            // "05.000.000" tidak pernah terbentuk.
            setRawAmount(event.target.value.replace(/\D/g, "").replace(/^0+/, ""))
          }
        />
      </div>

      <div className="space-y-1 border-t pt-3">
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">{t("pos.total")}</span>
          <span className="font-semibold tabular-nums">
            {formatCurrency(total)}
          </span>
        </div>
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">
            {t("pos.paid_amount")}
          </span>
          <span className="tabular-nums">{formatCurrency(amount)}</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="text-sm text-muted-foreground">
            {t("pos.outstanding")}
          </span>
          <span className="tabular-nums">{formatCurrency(outstanding)}</span>
        </div>
      </div>
      <div className="flex justify-end">
        <StatusBadge
          status={paymentStatus}
          label={t(`clinic.payment_status.${paymentStatus}`)}
          variantMap={PAYMENT_STATUS_VARIANTS}
        />
      </div>
    </div>
  )
}

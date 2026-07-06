import { useEffect, useState } from "react"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "./format.ts"

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
  const [amount, setAmount] = useState<number>(0)

  const covers = amount >= total && total > 0

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
        <Label>{t("pos.amount")}</Label>
        <Input
          type="number"
          min={0}
          value={amount}
          onChange={(e) => setAmount(Number(e.target.value))}
        />
      </div>

      <div className="flex items-center justify-between border-t pt-3">
        <span className="text-sm text-muted-foreground">{t("pos.total")}</span>
        <span className="font-semibold tabular-nums">{formatCurrency(total)}</span>
      </div>
      <div className="flex justify-end">
        <Badge variant={covers ? "default" : "secondary"}>
          {covers
            ? t("clinic.payment_status.paid")
            : t("clinic.payment_status.unpaid")}
        </Badge>
      </div>
    </div>
  )
}

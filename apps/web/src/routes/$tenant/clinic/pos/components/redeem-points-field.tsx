import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { MIN_REDEEM, capToBill, redeemValue } from "./loyalty-points.ts"

interface RedeemPointsFieldProps {
  /** Apa yang diketik kasir; string supaya kolomnya boleh kosong. */
  value: string
  onChange: (next: string) => void
  /** Saldo poin pasien terpilih. */
  balance: number
  /** Tagihan setelah potongan nota — batas atas penukaran. */
  payable: number
}

/**
 * Penukaran poin jadi potongan, di meja kasir.
 *
 * Kolomnya cuma muncul setelah pasiennya punya poin yang benar-benar bisa
 * dipakai: menawarkan penukaran kepada pasien bersaldo nol membuat kasir
 * menjelaskan hal yang sama berulang-ulang di depan antrean.
 *
 * Batas atasnya dua lapis dan keduanya sengaja terlihat — saldo pasien, dan
 * tagihan yang tersisa. Poin memotong yang harus dibayar, jadi penukaran yang
 * melebihi tagihan dipangkas, bukan ditolak: kasir yang menekan "tukar semua"
 * bermaksud "pakai sebisanya".
 */
export function RedeemPointsField({
  value,
  onChange,
  balance,
  payable,
}: RedeemPointsFieldProps) {
  const { t } = useTrans()

  // Yang benar-benar bisa dipakai pada tagihan ini, bukan seluruh saldo.
  const usable = capToBill(balance, payable)

  if (usable < MIN_REDEEM) return null

  const typed = Number(value)
  const points = Number.isFinite(typed) ? Math.max(0, Math.floor(typed)) : 0
  const applied = capToBill(Math.min(points, balance), payable)
  const amount = redeemValue(applied)
  // Diketik melebihi yang bisa dipakai: angkanya tetap dibiarkan berdiri di
  // kolom, tapi kasir diberi tahu berapa yang benar-benar terpakai sebelum
  // menyebut angkanya ke pasien.
  const capped = points > applied

  return (
    <div className="space-y-2 rounded-md border border-border/60 p-3">
      <div className="flex items-baseline justify-between gap-2">
        <Label htmlFor="redeem-points" className="text-xs">
          {t("pos.points_redeem")}
        </Label>
        <span className="text-2xs text-muted-foreground tabular-nums">
          {balance} {t("pos.loyalty_points_unit")}
        </span>
      </div>

      <div className="flex gap-2">
        <Input
          id="redeem-points"
          type="number"
          inputMode="numeric"
          step={1}
          min={0}
          max={usable}
          placeholder="0"
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="h-8 flex-1 text-xs tabular-nums"
        />

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-8 shrink-0 text-xs"
              onClick={() => onChange(String(usable))}
            >
              {t("pos.points_redeem_all")}
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            {t("pos.points_redeem_all")} — {usable}{" "}
            {t("pos.loyalty_points_unit")}
          </TooltipContent>
        </Tooltip>
      </div>

      {amount > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t("pos.points_redeem")}{" "}
          <span className="font-medium text-foreground tabular-nums">
            −{formatCurrency(amount)}
          </span>
          {capped ? (
            <span className="block text-2xs">{t("pos.points_capped")}</span>
          ) : null}
        </p>
      ) : (
        <p className="text-xs text-muted-foreground">
          {t("pos.points_redeem_hint")}
        </p>
      )}
    </div>
  )
}

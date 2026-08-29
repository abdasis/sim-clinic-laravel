import { HugeiconsIcon } from "@hugeicons/react"
import {
  Delete02Icon,
  MinusSignIcon,
  PlusSignIcon,
  Tag01Icon,
} from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { LineItem } from "../hooks/use-pos-cart.ts"

interface PosCartProps {
  items: LineItem[]
  total: number
  onStep: (key: string, delta: number) => void
  onRemove: (key: string) => void
  onClear: () => void
  /** Staf yang boleh dicatat sebagai penawar. */
  staff: { id: number; name: string }[]
  onOfferedBy: (key: string, userId: number | null) => void
}

export function PosCart({
  items,
  total,
  onStep,
  onRemove,
  onClear,
  staff,
  onOfferedBy,
}: PosCartProps) {
  const { t } = useTrans()

  const savings = items.reduce(
    (sum, item) =>
      sum +
      (item.basePrice === null
        ? 0
        : (item.basePrice - item.unitPrice) * item.qty),
    0,
  )

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold tracking-tight">
          {t("pos.cart.title")}
          {items.length > 0 ? (
            <span className="ml-1.5 text-xs font-normal text-muted-foreground tabular-nums">
              {t("pos.cart.items_count").replace(":count", String(items.length))}
            </span>
          ) : null}
        </h2>

        {items.length > 0 ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={onClear}
                className="h-7 px-2 text-xs text-muted-foreground hover:text-destructive"
              >
                {t("pos.cart.clear")}
              </Button>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-2">
              {t("pos.shortcut.clear")}
              <Kbd>Esc</Kbd>
            </TooltipContent>
          </Tooltip>
        ) : null}
      </div>

      {items.length === 0 ? (
        <EmptyState
          className="py-8"
          illustration="cart"
          title={t("pos.empty_cart")}
          description={t("pos.empty_cart_desc")}
        />
      ) : (
        <ul className="divide-y divide-border/50 rounded-lg border border-border/50">
          {items.map((item) => (
            <CartRow
              key={item.key}
              item={item}
              onStep={onStep}
              onRemove={onRemove}
              staff={staff}
              onOfferedBy={onOfferedBy}
            />
          ))}
        </ul>
      )}

      <div className="space-y-1 border-t border-border/50 pt-3">
        {/* Total hemat disebut sendiri: itu kalimat yang diucapkan kasir ke
            pasien, dan menghitungnya dari selisih dua angka di layar bukan
            pekerjaan yang boleh diserahkan ke orang yang sedang melayani. */}
        {savings > 0 ? (
          <div className="flex items-baseline justify-between">
            <span className="text-xs text-muted-foreground">
              {t("pos.cart.savings")}
            </span>
            <span className="text-xs font-medium tabular-nums text-emerald-600">
              −{formatCurrency(savings)}
            </span>
          </div>
        ) : null}

        <div className="flex items-baseline justify-between">
          <span className="text-sm text-muted-foreground">{t("pos.total")}</span>
          <span className="text-lg font-semibold tabular-nums">
            {formatCurrency(total)}
          </span>
        </div>
      </div>
    </div>
  )
}

function CartRow({
  item,
  onStep,
  onRemove,
  staff,
  onOfferedBy,
}: {
  item: LineItem
  onStep: (key: string, delta: number) => void
  onRemove: (key: string) => void
  staff: { id: number; name: string }[]
  onOfferedBy: (key: string, userId: number | null) => void
}) {
  const { t } = useTrans()
  const overStock = item.stock !== null && item.qty > item.stock

  return (
    // Dua baris, bukan satu: nama, stepper, total, dan tombol hapus berebut
    // lebar di panel 380px sampai totalnya terpotong. Dipisah begini semuanya
    // muat tanpa mengecilkan target sentuh.
    <li className="flex flex-col gap-1.5 p-2.5">
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{item.name}</p>
          <p className="flex flex-wrap items-baseline gap-1.5 text-xs text-muted-foreground">
            <span className="tabular-nums">
              {formatCurrency(item.unitPrice)}
            </span>
            {/* Harga asli dicoret di sebelahnya: kasir membaca keranjang saat
                menyebutkan tagihan, dan pasien yang datang karena promo perlu
                melihat potongannya — bukan cuma angka yang kebetulan lebih
                murah. */}
            {item.basePrice !== null ? (
              <span className="tabular-nums line-through opacity-70">
                {formatCurrency(item.basePrice)}
              </span>
            ) : null}
          </p>
          {item.promoName ? (
            <Badge
              variant="secondary"
              className="mt-1 max-w-full gap-1 font-normal"
            >
              <HugeiconsIcon
                icon={Tag01Icon}
                strokeWidth={2}
                className="size-3 shrink-0"
              />
              <span className="truncate">{item.promoName}</span>
            </Badge>
          ) : null}
        </div>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
              aria-label={`${t("pos.cart.remove")}: ${item.name}`}
              onClick={() => onRemove(item.key)}
            >
              <HugeiconsIcon
                icon={Delete02Icon}
                strokeWidth={2}
                className="size-3.5"
              />
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t("pos.cart.remove")}</TooltipContent>
        </Tooltip>
      </div>

      <div className="flex items-center justify-between gap-2">
        <div className="flex shrink-0 items-center gap-1">
          <Stepper
            label={t("pos.cart.decrease")}
            icon={MinusSignIcon}
            onClick={() => onStep(item.key, -1)}
          />
          <span
            className={cn(
              "w-6 text-center text-sm font-medium tabular-nums",
              overStock && "text-destructive",
            )}
          >
            {item.qty}
          </span>
          <Stepper
            label={t("pos.cart.increase")}
            icon={PlusSignIcon}
            onClick={() => onStep(item.key, 1)}
          />
        </div>

        {/* Tanpa lebar tetap: angka rupiah panjang tidak lagi terpotong. */}
        <span className="min-w-0 truncate text-right text-sm font-medium tabular-nums">
          {formatCurrency(item.unitPrice * item.qty)}
        </span>
      </div>

      {/* Penawar dipilih per baris, bukan sekali untuk seluruh nota: booster
          yang ditawarkan dokter bisa berdampingan dengan treatment yang
          ditawarkan terapis dalam satu kunjungan. */}
      <label className="flex items-center gap-1.5">
        <span className="shrink-0 text-2xs text-muted-foreground">
          {t("pos.offered_by")}
        </span>
        {/* NativeSelect sudah merender <select> sendiri dan meneruskan
            children ke dalamnya, jadi isinya harus berupa <option> langsung.
            Menaruh <select> di sini menghasilkan select di dalam select:
            yang terlihat kosong tanpa opsi, yang berisi opsi berukuran nol,
            dan kasir menekan sesuatu yang tidak pernah membuka apa pun. */}
        <NativeSelect
          size="sm"
          className="min-w-0 flex-1"
          aria-label={`${t("pos.offered_by")}: ${item.name}`}
          value={item.offeredBy === null ? "" : String(item.offeredBy)}
          onChange={(event) =>
            onOfferedBy(
              item.key,
              event.target.value === "" ? null : Number(event.target.value),
            )
          }
        >
          <NativeSelectOption value="">
            {t("pos.offered_by_none")}
          </NativeSelectOption>
          {staff.map((member) => (
            <NativeSelectOption key={member.id} value={member.id}>
              {member.name}
            </NativeSelectOption>
          ))}
        </NativeSelect>
      </label>

      {overStock ? (
        <p className="text-xs font-medium text-destructive">
          {t("pos.insufficient_stock")}
        </p>
      ) : null}
    </li>
  )
}

function Stepper({
  label,
  icon,
  onClick,
}: {
  label: string
  icon: React.ComponentProps<typeof HugeiconsIcon>["icon"]
  onClick: () => void
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="size-6 rounded-md"
          aria-label={label}
          onClick={onClick}
        >
          <HugeiconsIcon icon={icon} strokeWidth={2} className="size-3" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

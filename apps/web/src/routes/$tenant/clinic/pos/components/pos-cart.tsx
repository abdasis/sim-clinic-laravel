import { HugeiconsIcon } from "@hugeicons/react"
import {
  Delete02Icon,
  MinusSignIcon,
  PlusSignIcon,
} from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
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
}

export function PosCart({ items, total, onStep, onRemove, onClear }: PosCartProps) {
  const { t } = useTrans()

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
            />
          ))}
        </ul>
      )}

      <div className="flex items-baseline justify-between border-t border-border/50 pt-3">
        <span className="text-sm text-muted-foreground">{t("pos.total")}</span>
        <span className="text-lg font-semibold tabular-nums">
          {formatCurrency(total)}
        </span>
      </div>
    </div>
  )
}

function CartRow({
  item,
  onStep,
  onRemove,
}: {
  item: LineItem
  onStep: (key: string, delta: number) => void
  onRemove: (key: string) => void
}) {
  const { t } = useTrans()
  const overStock = item.stock !== null && item.qty > item.stock

  return (
    <li className="flex items-start gap-2 p-2.5">
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{item.name}</p>
        <p className="text-xs text-muted-foreground tabular-nums">
          {formatCurrency(item.unitPrice)}
        </p>
        {overStock ? (
          <p className="mt-0.5 text-xs font-medium text-destructive">
            {t("pos.insufficient_stock")}
          </p>
        ) : null}
      </div>

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

      <span className="w-24 shrink-0 text-right text-sm font-medium tabular-nums">
        {formatCurrency(item.unitPrice * item.qty)}
      </span>

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
            <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} className="size-3.5" />
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t("pos.cart.remove")}</TooltipContent>
      </Tooltip>
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

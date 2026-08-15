import { HugeiconsIcon } from "@hugeicons/react"
import { PackageIcon, StethoscopeIcon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { cn } from "#/lib/utils.ts"
import type { CatalogEntry, StockStatus } from "../hooks/use-pos-catalog.ts"

interface ProductCardProps {
  entry: CatalogEntry
  onAdd: (entry: CatalogEntry) => void
}

const STOCK_TONE: Record<Exclude<StockStatus, "n/a">, string> = {
  available: "border-border/60 text-muted-foreground",
  low: "border-amber-500/40 text-amber-600 dark:text-amber-400",
  out: "border-destructive/40 text-destructive",
}

/**
 * Kartu katalog. Sengaja `<button>` supaya bisa dijangkau Tab dan ditambahkan
 * dengan Enter — kasir yang cepat jarang melepas tangan dari keyboard.
 */
export function ProductCard({ entry, onAdd }: ProductCardProps) {
  const { t } = useTrans()
  const isService = entry.kind === "service"
  const soldOut = entry.stockStatus === "out"

  return (
    <button
      type="button"
      data-pos-card
      disabled={soldOut}
      onClick={() => onAdd(entry)}
      aria-label={`${t("pos.catalog.add")}: ${entry.name}`}
      className={cn(
        "group flex h-full flex-col items-start gap-2 rounded-lg border border-border/50 bg-card p-3 text-left shadow-sm",
        "transition-[transform,border-color,background-color] duration-150 ease-out",
        "hover:-translate-y-0.5 hover:border-border hover:bg-accent/40",
        "focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:outline-none",
        "active:translate-y-0 active:bg-accent/60",
        "disabled:pointer-events-none disabled:opacity-55",
      )}
    >
      <span className="flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:text-foreground">
        <HugeiconsIcon
          icon={isService ? StethoscopeIcon : PackageIcon}
          strokeWidth={2}
          className="size-4"
        />
      </span>

      <span className="line-clamp-2 text-sm leading-snug font-medium">
        {entry.name}
      </span>

      {entry.promoName ? (
        <Badge
          variant="outline"
          className="max-w-full shrink-0 gap-1 border-primary/40 px-1.5 text-xxs font-medium text-primary"
        >
          <span className="truncate">{entry.promoName}</span>
        </Badge>
      ) : null}

      <span className="mt-auto flex w-full items-center justify-between gap-2">
        <span className="flex min-w-0 flex-col">
          <span className="text-sm font-semibold tabular-nums">
            {formatCurrency(entry.price)}
          </span>
          {/* Harga coret hanya muncul saat ada promo — kasir bisa menyebutkan
              potongannya ke pasien tanpa membuka menu promo. */}
          {entry.basePrice !== null ? (
            <span className="text-xxs text-muted-foreground line-through tabular-nums">
              {formatCurrency(entry.basePrice)}
            </span>
          ) : null}
        </span>
        {isService ? null : (
          <Badge
            variant="outline"
            className={cn(
              "shrink-0 px-1.5 text-xxs font-medium",
              STOCK_TONE[entry.stockStatus as Exclude<StockStatus, "n/a">],
            )}
          >
            {soldOut
              ? t("pos.stock.out")
              : `${entry.stock}${entry.unit ? ` ${entry.unit}` : ""}`}
          </Badge>
        )}
      </span>
    </button>
  )
}

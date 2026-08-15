import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { cn } from "#/lib/utils.ts"

/** Satu kelompok preferensi: judul, keterangan, lalu kontrolnya. */
export function SectionCard({
  title,
  description,
  children,
}: {
  title: string
  description: string
  children: React.ReactNode
}) {
  return (
    <Card className="border-border/50 shadow-sm">
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  )
}

/**
 * Pilihan berbentuk kartu kecil. Dipakai untuk mode dan varian sidebar —
 * keduanya pilihan tunggal dengan ikon, jadi bentuknya disamakan.
 */
export function OptionTile({
  icon,
  label,
  hint,
  selected,
  onSelect,
}: {
  icon: IconSvgElement
  label: string
  hint?: string
  selected: boolean
  onSelect: () => void
}) {
  const tile = (
    <button
      type="button"
      aria-pressed={selected}
      onClick={onSelect}
      className={cn(
        "flex w-full flex-col items-center gap-1.5 rounded-md border px-3 py-3",
        "transition-[border-color,background-color,transform] duration-150 ease-out",
        "hover:-translate-y-px hover:bg-accent/40",
        "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
        "active:translate-y-0",
        selected ? "border-foreground/40 bg-muted" : "border-border/50",
      )}
    >
      <HugeiconsIcon
        icon={icon}
        strokeWidth={2}
        className={cn(
          "size-4 transition-colors",
          selected ? "text-foreground" : "text-muted-foreground",
        )}
      />
      <span
        className={cn("text-xs", selected ? "font-medium" : "text-muted-foreground")}
      >
        {label}
      </span>
    </button>
  )

  if (!hint) return tile

  return (
    <Tooltip>
      <TooltipTrigger asChild>{tile}</TooltipTrigger>
      <TooltipContent>{hint}</TooltipContent>
    </Tooltip>
  )
}

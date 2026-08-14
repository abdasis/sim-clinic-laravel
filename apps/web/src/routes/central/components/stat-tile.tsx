import type { LucideIcon } from "lucide-react"

import { Skeleton } from "#/components/ui/skeleton.tsx"
import { cn } from "#/lib/utils.ts"

interface StatTileProps {
  label: string
  value: number | undefined
  icon: LucideIcon
  loading?: boolean
  className?: string
}

export function StatTile({
  label,
  value,
  icon: Icon,
  loading,
  className,
}: StatTileProps) {
  return (
    <div
      className={cn(
        "rounded-lg border border-border/60 bg-card p-4 shadow-sm transition-colors hover:border-border",
        className,
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
          {label}
        </span>
        <Icon className="size-4 text-muted-foreground" />
      </div>

      {loading ? (
        <Skeleton className="mt-3 h-8 w-16" />
      ) : (
        <p className="mt-2 text-3xl font-semibold tabular-nums tracking-tight">
          {value ?? 0}
        </p>
      )}
    </div>
  )
}

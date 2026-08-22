import { HugeiconsIcon } from "@hugeicons/react"
import type { IconSvgElement } from "@hugeicons/react"

import { cn } from "#/lib/utils.ts"

interface ReportStatCardProps {
  icon: IconSvgElement
  label: string
  value: string
  hint: string
  /** Kelas latar batang tepi kiri, mis. `bg-chart-cat-1`. */
  accent: string
  /** Satu kartu per blok boleh menonjol; lebih dari itu tidak menonjolkan apa pun. */
  emphasis?: boolean
}

/**
 * Satu kartu angka pembuka laporan.
 *
 * Warnanya duduk di batang tepi kiri dan di lencana ikon, bukan di teksnya —
 * angka tetap memakai warna teks biasa supaya terbaca di terang maupun gelap.
 *
 * Dipakai bersama oleh laporan bulanan dan omzet supaya angka pembuka di
 * seluruh tab laporan punya bentuk yang sama; sebelumnya tab omzet memakai
 * kartu polos sendiri dan terbaca seperti halaman dari aplikasi lain.
 */
export function ReportStatCard({
  icon,
  label,
  value,
  hint,
  accent,
  emphasis = false,
}: ReportStatCardProps) {
  return (
    <div
      className={cn(
        "group relative overflow-hidden rounded-lg border p-4 transition-colors duration-150",
        emphasis
          ? "border-primary/60 bg-primary text-primary-foreground"
          : "border-border/60 bg-card hover:border-border",
      )}
    >
      <span
        aria-hidden
        className={cn(
          "absolute inset-y-0 left-0 w-[3px]",
          emphasis ? "bg-primary-foreground/50" : accent,
        )}
      />

      <div className="flex items-center gap-2 pl-1.5">
        <span
          aria-hidden
          className={cn(
            "flex size-6 shrink-0 items-center justify-center rounded-md",
            emphasis ? "bg-primary-foreground/15" : "bg-muted",
          )}
        >
          <HugeiconsIcon
            icon={icon}
            strokeWidth={2}
            className={cn(
              "size-3.5",
              emphasis ? "text-primary-foreground" : "text-muted-foreground",
            )}
          />
        </span>
        <p
          className={cn(
            "text-2xs font-semibold tracking-wider uppercase",
            emphasis ? "text-primary-foreground/80" : "text-muted-foreground",
          )}
        >
          {label}
        </p>
      </div>

      <p className="mt-2 pl-1.5 text-2xl leading-tight font-semibold tabular-nums">
        {value}
      </p>
      <p
        className={cn(
          "mt-0.5 pl-1.5 text-xs",
          emphasis ? "text-primary-foreground/70" : "text-muted-foreground",
        )}
      >
        {hint}
      </p>
    </div>
  )
}

interface SectionHeaderProps {
  step: number
  title: string
  hint: string
}

/** Kepala seksi bernomor: nomor kecil menjaga urutan terbaca tanpa ikon. */
export function SectionHeader({ step, title, hint }: SectionHeaderProps) {
  return (
    <div className="flex items-start gap-2.5">
      <span
        aria-hidden
        className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground tabular-nums"
      >
        {step}
      </span>
      <div className="min-w-0">
        <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
        <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground text-pretty">
          {hint}
        </p>
      </div>
    </div>
  )
}

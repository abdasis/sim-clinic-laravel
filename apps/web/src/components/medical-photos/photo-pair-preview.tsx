import { cn } from "#/lib/utils.ts"
import { countByType, pairPhotos, photoSrc, type PhotoRow } from "./photo-types.ts"

const THUMB = "size-9 shrink-0 rounded-sm border object-cover"

/**
 * Cuplikan pasangan pertama sebelum/sesudah, seukuran sel tabel.
 *
 * Dipakai di riwayat pasien: barisnya ada belasan, jadi yang ditampilkan
 * cukup pasangan pertamanya — sisanya diwakili hitungan, dan detailnya
 * dibuka lewat baris yang memang sudah jadi tautan ke rekam medisnya.
 */
export function PhotoPairPreview({
  photos = [],
  labels,
}: {
  photos?: PhotoRow[]
  labels: { before: string; after: string; none: string }
}) {
  const pairs = pairPhotos(photos)

  if (pairs.length === 0) {
    return <span className="text-muted-foreground/60">{labels.none}</span>
  }

  const [first] = pairs
  const counts = countByType(photos)
  const rest = counts.before + counts.after - (first.before ? 1 : 0) - (first.after ? 1 : 0)

  return (
    <div className="flex items-center gap-1">
      {(["before", "after"] as const).map((type) =>
        first[type] ? (
          <img
            key={type}
            src={photoSrc(first[type])}
            alt={labels[type]}
            loading="lazy"
            className={cn(THUMB, "border-border/60 bg-muted/30")}
          />
        ) : (
          <span
            key={type}
            aria-label={labels[type]}
            className={cn(THUMB, "border-dashed border-border/60 bg-muted/20")}
          />
        ),
      )}
      {rest > 0 ? (
        <span className="text-[11px] text-muted-foreground/70 tabular-nums">
          +{rest}
        </span>
      ) : null}
    </div>
  )
}

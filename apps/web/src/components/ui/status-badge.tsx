import { Badge } from "#/components/ui/badge.tsx"

type BadgeVariant = React.ComponentProps<typeof Badge>["variant"]

interface StatusBadgeProps {
  /** Nilai status mentah dari API, mis. "archived" atau "partially_paid". */
  status: string
  /** Teks siap tampil; sudah lewat t() di pemanggil. */
  label: string
  /** Pemetaan status ke variant badge; status tak dikenal pakai default. */
  variantMap?: Record<string, BadgeVariant>
  className?: string
}

/**
 * Badge status yang seragam lintas modul — sebelumnya tiap halaman menulis
 * kondisional variant sendiri sehingga status yang sama bisa tampil beda.
 */
export function StatusBadge({
  status,
  label,
  variantMap,
  className,
}: StatusBadgeProps) {
  return (
    <Badge variant={variantMap?.[status] ?? "secondary"} className={className}>
      {label}
    </Badge>
  )
}

/** Peta status pembayaran POS: belum dibayar, dibayar sebagian, lunas. */
export const PAYMENT_STATUS_VARIANTS: Record<string, BadgeVariant> = {
  unpaid: "destructive",
  partially_paid: "outline",
  paid: "default",
}

/** Peta status master data yang bisa diarsipkan. */
export const ARCHIVABLE_STATUS_VARIANTS: Record<string, BadgeVariant> = {
  active: "default",
  archived: "secondary",
}

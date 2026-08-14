import { Button } from "#/components/ui/button.tsx"

type ButtonProps = React.ComponentProps<typeof Button>

interface CtaLinkProps {
  tenant: string
  label?: string | null
  type?: string | null
  value?: string | null
  variant?: ButtonProps["variant"]
  size?: ButtonProps["size"]
  className?: string
}

/**
 * Tombol ajakan yang tujuannya diatur admin. `cta_type` menentukan cara
 * `cta_value` dibaca — route internal, alamat luar, atau nomor WhatsApp.
 */
export function CtaLink({
  tenant,
  label,
  type,
  value,
  variant,
  size,
  className,
}: CtaLinkProps) {
  if (!label || !value) return null

  // Tujuan internal diketik admin, jadi tidak ada di daftar route yang
  // dikenal saat kompilasi — pakai anchor biasa, bukan Link bertipe.
  if (type === "route_internal") {
    return (
      <Button asChild variant={variant} size={size} className={className}>
        <a href={internalHref(tenant, value)}>{label}</a>
      </Button>
    )
  }

  const href =
    type === "whatsapp"
      ? `https://wa.me/${normalizeWhatsapp(value)}`
      : value

  return (
    <Button asChild variant={variant} size={size} className={className}>
      <a href={href} target="_blank" rel="noreferrer noopener">
        {label}
      </a>
    </Button>
  )
}

/** Path yang diatur admin selalu relatif terhadap tenant-nya. */
export function internalHref(tenant: string, value: string): string {
  return `/${tenant}${value.startsWith("/") ? value : `/${value}`}`
}

/** wa.me menolak spasi, tanda hubung, dan awalan 0 — normalkan ke 62. */
export function normalizeWhatsapp(value: string): string {
  const digits = value.replace(/\D/g, "")

  return digits.startsWith("0") ? `62${digits.slice(1)}` : digits
}

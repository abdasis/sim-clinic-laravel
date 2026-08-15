import { Button } from "#/components/ui/button.tsx"
import type { Translatable } from "#/lib/company-locale.ts"
import { useContentText } from "./locale-context.tsx"

type ButtonProps = React.ComponentProps<typeof Button>

interface CtaLinkProps {
  tenant: string
  label?: Translatable<string> | null
  type?: string | null
  url?: string | null
  variant?: ButtonProps["variant"]
  size?: ButtonProps["size"]
  className?: string
}

/**
 * Tombol ajakan yang tujuannya diatur admin. `cta_type` menentukan cara
 * `cta_url` dibaca — path internal, alamat luar, atau tautan WhatsApp.
 */
export function CtaLink({
  tenant,
  label,
  type,
  url,
  variant,
  size,
  className,
}: CtaLinkProps) {
  const text = useContentText()
  const caption = text(label)

  if (!caption || !url) return null

  // Tujuan internal diketik admin, jadi tidak ada di daftar route yang
  // dikenal saat kompilasi — pakai anchor biasa, bukan Link bertipe.
  const isInternal = type === "route_internal" || type === "route_root"

  return (
    <Button asChild variant={variant} size={size} className={className}>
      <a
        href={ctaHref(tenant, type, url)}
        {...(isInternal ? {} : { target: "_blank", rel: "noreferrer noopener" })}
      >
        {caption}
      </a>
    </Button>
  )
}

/** Path yang diatur admin selalu relatif terhadap tenant-nya. */
export function internalHref(tenant: string, value: string): string {
  return `/${tenant}${value.startsWith("/") ? value : `/${value}`}`
}

/**
 * Terjemahkan `cta_url` sesuai jenisnya. Untuk WhatsApp, kolomnya kerap
 * diisi nomor telepon biasa alih-alih tautan penuh — keduanya diterima.
 */
export function ctaHref(
  tenant: string,
  type: string | null | undefined,
  url: string,
): string {
  // Halaman marketing hidup di akar situs, bukan di dalam tenant.
  if (type === "route_root") return url.startsWith("/") ? url : `/${url}`

  if (type === "route_internal") return internalHref(tenant, url)

  if (type === "whatsapp" && !/^https?:\/\//i.test(url)) {
    return `https://wa.me/${normalizeWhatsapp(url)}`
  }

  return url
}

/** wa.me menolak spasi, tanda hubung, dan awalan 0 — normalkan ke 62. */
export function normalizeWhatsapp(value: string): string {
  const digits = value.replace(/\D/g, "")

  return digits.startsWith("0") ? `62${digits.slice(1)}` : digits
}

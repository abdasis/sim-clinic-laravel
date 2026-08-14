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
  const isInternal = type === "route_internal"

  return (
    <Button asChild variant={variant} size={size} className={className}>
      <a
        href={isInternal ? internalHref(tenant, url) : url}
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

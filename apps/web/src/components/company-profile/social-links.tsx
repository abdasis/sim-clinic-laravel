import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import {
  Facebook01Icon,
  InstagramIcon,
  Link01Icon,
  TiktokIcon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons"

import type { SocialLink } from "#/hooks/use-company-profile.ts"
import { cn } from "#/lib/utils.ts"

/**
 * Lambang platform yang dikenali. Yang di luar daftar tetap tampil sebagai
 * tautan berikon rantai — lebih baik daripada hilang diam-diam saat klinik
 * menambahkan kanal yang belum kita dukung.
 */
const ICONS: Record<string, IconSvgElement> = {
  instagram: InstagramIcon,
  facebook: Facebook01Icon,
  tiktok: TiktokIcon,
  whatsapp: WhatsappIcon,
}

/** Warna hover mengikuti identitas platformnya — dikenali tanpa dibaca. */
const HOVER: Record<string, string> = {
  instagram: "hover:border-[#d62976]/40 hover:text-[#d62976]",
  facebook: "hover:border-[#1877f2]/40 hover:text-[#1877f2]",
  tiktok: "hover:border-foreground/40 hover:text-foreground",
  whatsapp: "hover:border-[#25d366]/50 hover:text-[#128c4a]",
}

export function SocialLinks({
  links,
  className,
}: {
  links: SocialLink[]
  className?: string
}) {
  if (links.length === 0) return null

  return (
    <ul className={cn("flex flex-wrap items-center gap-2", className)}>
      {links.map((social) => {
        const key = social.platform.toLowerCase()

        return (
          <li key={social.platform}>
            <a
              href={social.url}
              target="_blank"
              rel="noreferrer noopener"
              aria-label={social.platform}
              title={social.platform}
              className={cn(
                "flex size-9 items-center justify-center rounded-full border border-border/60 bg-background text-muted-foreground",
                "transition-[transform,box-shadow,border-color,color] duration-150 ease-out hover:-translate-y-0.5 hover:shadow-sm active:scale-[0.96]",
                "focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none",
                HOVER[key] ?? "hover:border-foreground/30 hover:text-foreground",
              )}
            >
              <HugeiconsIcon
                icon={ICONS[key] ?? Link01Icon}
                strokeWidth={1.8}
                className="size-[18px]"
              />
            </a>
          </li>
        )
      })}
    </ul>
  )
}

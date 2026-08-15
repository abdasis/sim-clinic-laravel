import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import {
  ArrowUp01Icon,
  InstagramIcon,
  Link01Icon,
  ShoppingBag01Icon,
  TiktokIcon,
  YoutubeIcon,
} from "@hugeicons/core-free-icons"

import { useContentText } from "#/components/company-profile/locale-context.tsx"
import type {
  CompanyNavItem,
  CompanySettings,
} from "#/hooks/use-company-profile.ts"
import type { ContentLocale } from "#/lib/company-locale.ts"

/** Ikon per platform; yang tidak dikenal memakai ikon tautan biasa. */
const SOCIAL_ICONS: Record<string, IconSvgElement> = {
  instagram: InstagramIcon,
  tiktok: TiktokIcon,
  youtube: YoutubeIcon,
}

interface LandingFooterProps {
  settings: CompanySettings | null
  items: CompanyNavItem[]
  locale: ContentLocale
}

export function LandingFooter({ settings, items, locale }: LandingFooterProps) {
  const text = useContentText()
  const isEnglish = locale === "en"

  const socials = settings?.social_links ?? []
  const marketplaces = settings?.marketplace_links ?? []

  return (
    <footer className="border-t border-border/50 bg-muted/30">
      <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-2">
          <p className="text-sm font-semibold tracking-tight">
            {text(settings?.site_name)}
          </p>
          <p className="text-sm leading-relaxed text-muted-foreground">
            {isEnglish
              ? "Beauty, laser, and hair removal clinic with certified doctors."
              : "Klinik kecantikan, laser, dan hair removal dengan dokter bersertifikasi."}
          </p>
        </div>

        <FooterColumn heading={isEnglish ? "Talk To Us" : "Hubungi Kami"}>
          {items.map((item) =>
            item.url ? (
              <li key={item.id}>
                <a
                  href={
                    item.link_type === "external"
                      ? item.url
                      : `${item.url}?lang=${locale}`
                  }
                  {...(item.link_type === "external"
                    ? { target: "_blank", rel: "noreferrer noopener" }
                    : {})}
                  className="text-sm text-muted-foreground no-underline transition-colors hover:text-foreground"
                >
                  {text(item.label)}
                </a>
              </li>
            ) : null,
          )}
        </FooterColumn>

        <FooterColumn heading={isEnglish ? "Social Media" : "Media Sosial"}>
          {socials.map((social) => (
            <li key={social.platform}>
              <IconLink
                href={social.url}
                icon={SOCIAL_ICONS[social.icon ?? ""] ?? Link01Icon}
                label={social.platform}
              />
            </li>
          ))}
        </FooterColumn>

        <FooterColumn heading="Official Marketplace">
          {marketplaces.map((marketplace) => (
            <li key={marketplace.name}>
              <IconLink
                href={marketplace.url}
                icon={ShoppingBag01Icon}
                label={marketplace.name}
              />
            </li>
          ))}
        </FooterColumn>
      </div>

      <div className="border-t border-border/50">
        <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4">
          <p className="text-xs text-muted-foreground">
            {settings?.copyright_text}
          </p>
          <button
            type="button"
            onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
            className="flex items-center gap-1 rounded-sm text-xs text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
          >
            <HugeiconsIcon
              icon={ArrowUp01Icon}
              strokeWidth={2}
              className="size-3.5"
            />
            {isEnglish ? "Back to top" : "Kembali ke atas"}
          </button>
        </div>
      </div>
    </footer>
  )
}

function FooterColumn({
  heading,
  children,
}: {
  heading: string
  children: React.ReactNode
}) {
  return (
    <div className="space-y-2.5">
      <p className="text-xs font-medium tracking-widest text-muted-foreground uppercase">
        {heading}
      </p>
      <ul className="space-y-1.5">{children}</ul>
    </div>
  )
}

function IconLink({
  href,
  icon,
  label,
}: {
  href: string
  icon: IconSvgElement
  label: string
}) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noreferrer noopener"
      className="flex items-center gap-2 text-sm text-muted-foreground no-underline transition-colors hover:text-foreground"
    >
      <HugeiconsIcon icon={icon} strokeWidth={2} className="size-4" />
      {label}
    </a>
  )
}

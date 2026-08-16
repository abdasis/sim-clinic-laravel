import type {
  CompanyNavItem,
  CompanySettings,
} from "#/hooks/use-company-profile.ts"
import { internalHref } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"

interface FooterLabels {
  explore: string
  social: string
  shop: string
}

interface CompanyFooterProps {
  tenant: string
  settings: CompanySettings | null
  items: CompanyNavItem[]
  labels: FooterLabels
}

export function CompanyFooter({
  tenant,
  settings,
  items,
  labels,
}: CompanyFooterProps) {
  const text = useContentText()
  const brand = text(settings?.site_name) ?? tenant
  const socials = settings?.social_links ?? []
  const marketplaces = settings?.marketplace_links ?? []

  return (
    <footer className="border-t border-border/50 bg-muted/30">
      {/* Kolom footer diberi kepala. Tanpa label, tiga kolom tautan di bawah
          terbaca sebagai satu daftar panjang yang terpotong -- pengunjung
          tidak tahu mana menu, mana media sosial, mana toko. */}
      <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div className="space-y-2.5">
          <p className="text-base font-semibold tracking-tight">{brand}</p>
          {settings?.copyright_text ? (
            <p className="max-w-xs text-sm leading-relaxed text-pretty text-muted-foreground">
              {settings.copyright_text}
            </p>
          ) : null}
        </div>

        {items.length > 0 ? (
          <FooterColumn label={labels.explore}>
            <nav className="flex flex-col gap-2">
              {items.map((item) => (
                <FooterLink key={item.id} tenant={tenant} item={item} />
              ))}
            </nav>
          </FooterColumn>
        ) : null}

        {socials.length > 0 ? (
          <FooterColumn label={labels.social}>
            <ul className="flex flex-col gap-2 text-sm">
              {socials.map((social) => (
                <li key={social.platform}>
                  <a
                    href={social.url}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="text-muted-foreground capitalize transition-colors hover:text-foreground"
                  >
                    {social.platform}
                  </a>
                </li>
              ))}
            </ul>
          </FooterColumn>
        ) : null}

        {marketplaces.length > 0 ? (
          <FooterColumn label={labels.shop}>
            <ul className="flex flex-col gap-2 text-sm">
              {marketplaces.map((marketplace) => (
                <li key={marketplace.name}>
                  <a
                    href={marketplace.url}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="text-muted-foreground transition-colors hover:text-foreground"
                  >
                    {marketplace.name}
                  </a>
                </li>
              ))}
            </ul>
          </FooterColumn>
        ) : null}
      </div>

      <div className="border-t border-border/50">
        <p className="mx-auto w-full max-w-6xl px-4 py-4 text-xs text-muted-foreground">
          &copy; {new Date().getFullYear()} {settings?.copyright_text ?? brand}
        </p>
      </div>
    </footer>
  )
}

function FooterColumn({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="space-y-3">
      <p className="text-2xs font-semibold tracking-[0.16em] text-foreground/60 uppercase">
        {label}
      </p>
      {children}
    </div>
  )
}

function FooterLink({ tenant, item }: { tenant: string; item: CompanyNavItem }) {
  const text = useContentText()

  if (!item.url) return null

  const href =
    item.link_type === "route_internal"
      ? internalHref(tenant, item.url)
      : item.url

  return (
    <a
      href={href}
      className="text-sm text-muted-foreground transition-colors hover:text-foreground"
      {...(item.link_type === "external"
        ? { target: "_blank", rel: "noreferrer noopener" }
        : {})}
    >
      {text(item.label)}
    </a>
  )
}

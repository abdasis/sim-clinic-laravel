import type {
  CompanyNavItem,
  CompanySettings,
} from "#/hooks/use-company-profile.ts"
import { internalHref } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"

interface CompanyFooterProps {
  tenant: string
  settings: CompanySettings | null
  items: CompanyNavItem[]
}

export function CompanyFooter({ tenant, settings, items }: CompanyFooterProps) {
  const text = useContentText()
  const brand = text(settings?.site_name) ?? tenant
  const socials = settings?.social_links ?? []
  const marketplaces = settings?.marketplace_links ?? []

  return (
    <footer className="border-t border-border/50 bg-muted/30">
      <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-2">
          <p className="text-sm font-semibold tracking-tight">{brand}</p>
          {settings?.copyright_text ? (
            <p className="text-sm leading-relaxed text-muted-foreground">
              {settings.copyright_text}
            </p>
          ) : null}
        </div>

        {items.length > 0 ? (
          <nav className="flex flex-col gap-1.5">
            {items.map((item) => (
              <FooterLink key={item.id} tenant={tenant} item={item} />
            ))}
          </nav>
        ) : null}

        {socials.length > 0 ? (
          <ul className="flex flex-col gap-1.5 text-sm">
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
        ) : null}

        {marketplaces.length > 0 ? (
          <ul className="flex flex-col gap-1.5 text-sm">
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

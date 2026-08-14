import type {
  CompanyNavItem,
  CompanySettings,
} from "#/hooks/use-company-profile.ts"
import { internalHref } from "./cta-link.tsx"

interface CompanyFooterProps {
  tenant: string
  settings: CompanySettings | null
  items: CompanyNavItem[]
}

export function CompanyFooter({ tenant, settings, items }: CompanyFooterProps) {
  const brand = settings?.brand_name ?? tenant
  const socials = Object.entries(settings?.social_links ?? {})

  return (
    <footer className="border-t border-border/50 bg-muted/30">
      <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-2">
          <p className="text-sm font-semibold tracking-tight">{brand}</p>
          {settings?.tagline ? (
            <p className="text-sm leading-relaxed text-muted-foreground">
              {settings.tagline}
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

        <address className="space-y-1.5 text-sm text-muted-foreground not-italic">
          {settings?.address ? <p>{settings.address}</p> : null}
          {settings?.phone ? (
            <p>
              <a
                href={`tel:${settings.phone}`}
                className="transition-colors hover:text-foreground"
              >
                {settings.phone}
              </a>
            </p>
          ) : null}
          {settings?.email ? (
            <p>
              <a
                href={`mailto:${settings.email}`}
                className="transition-colors hover:text-foreground"
              >
                {settings.email}
              </a>
            </p>
          ) : null}
        </address>

        {socials.length > 0 ? (
          <ul className="flex flex-col gap-1.5 text-sm">
            {socials.map(([name, url]) => (
              <li key={name}>
                <a
                  href={url}
                  target="_blank"
                  rel="noreferrer noopener"
                  className="text-muted-foreground capitalize transition-colors hover:text-foreground"
                >
                  {name}
                </a>
              </li>
            ))}
          </ul>
        ) : null}
      </div>

      <div className="border-t border-border/50">
        <p className="mx-auto w-full max-w-6xl px-4 py-4 text-xs text-muted-foreground">
          &copy; {new Date().getFullYear()} {brand}
        </p>
      </div>
    </footer>
  )
}

function FooterLink({ tenant, item }: { tenant: string; item: CompanyNavItem }) {
  const className =
    "text-sm text-muted-foreground transition-colors hover:text-foreground"

  const href =
    item.link_type === "route_internal"
      ? internalHref(tenant, item.link_value)
      : item.link_value

  return (
    <a
      href={href}
      className={className}
      {...(item.link_type === "external"
        ? { target: "_blank", rel: "noreferrer noopener" }
        : {})}
    >
      {item.label}
    </a>
  )
}

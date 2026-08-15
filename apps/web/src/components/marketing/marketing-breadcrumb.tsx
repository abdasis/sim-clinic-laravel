import { Link } from "@tanstack/react-router"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "#/components/ui/breadcrumb.tsx"
import { useContentLocale } from "#/components/company-profile/locale-context.tsx"

export interface MarketingCrumb {
  label: string
  /** Kosongkan untuk item terakhir; item terakhir memang bukan tautan. */
  href?: string
}

/**
 * Jejak halaman marketing. Item pertama selalu beranda, jadi pemanggil
 * cukup menyebut sisanya.
 */
export function MarketingBreadcrumb({ items }: { items: MarketingCrumb[] }) {
  const locale = useContentLocale()
  const home = locale === "en" ? "Home" : "Beranda"

  return (
    <Breadcrumb className="mb-6">
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink asChild>
            <Link to="/" search={{ lang: locale }}>
              {home}
            </Link>
          </BreadcrumbLink>
          <BreadcrumbSeparator />
        </BreadcrumbItem>

        {items.map((item, index) => {
          const isLast = index === items.length - 1

          return (
            <BreadcrumbItem key={item.label}>
              {isLast || !item.href ? (
                <BreadcrumbPage>{item.label}</BreadcrumbPage>
              ) : (
                <>
                  <BreadcrumbLink asChild>
                    <a href={`${item.href}?lang=${locale}`}>{item.label}</a>
                  </BreadcrumbLink>
                  <BreadcrumbSeparator />
                </>
              )}
            </BreadcrumbItem>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}

/** Bingkai isi halaman marketing bagian dalam. */
export function MarketingPage({
  crumbs,
  title,
  description,
  children,
}: {
  crumbs: MarketingCrumb[]
  title: string
  description?: string
  children: React.ReactNode
}) {
  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-10">
      <MarketingBreadcrumb items={crumbs} />
      <header className="mb-6 max-w-2xl">
        <h1 className="text-2xl font-semibold tracking-tight text-balance">
          {title}
        </h1>
        {description ? (
          <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground text-pretty">
            {description}
          </p>
        ) : null}
      </header>
      {children}
    </main>
  )
}

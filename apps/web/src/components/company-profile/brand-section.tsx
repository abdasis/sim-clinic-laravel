import type { CompanyBrand } from "#/hooks/use-company-profile.ts"
import { SectionShell } from "./section-shell.tsx"

interface BrandSectionProps {
  id?: string
  heading?: string
  items: CompanyBrand[]
}

export function BrandSection({ id, heading, items }: BrandSectionProps) {
  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading}>
      <ul className="flex flex-wrap items-center gap-x-8 gap-y-6">
        {items.map((brand) => {
          const logo = brand.logo_url ? (
            <img
              src={brand.logo_url}
              alt={brand.name}
              loading="lazy"
              // Logo brand datang dengan warna yang berbeda-beda; disamakan
              // jadi abu lalu berwarna saat disentuh.
              className="h-8 w-auto opacity-60 grayscale transition-all duration-300 hover:opacity-100 hover:grayscale-0"
            />
          ) : (
            <span className="text-sm font-medium text-muted-foreground">
              {brand.name}
            </span>
          )

          return (
            <li key={brand.id}>
              {brand.url ? (
                <a
                  href={brand.url}
                  target="_blank"
                  rel="noreferrer noopener"
                  className="inline-flex rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                  {logo}
                </a>
              ) : (
                logo
              )}
            </li>
          )
        })}
      </ul>
    </SectionShell>
  )
}

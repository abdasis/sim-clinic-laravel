import type { CompanyBrand } from "#/hooks/use-company-profile.ts"
import { useContentText } from "./locale-context.tsx"
import { SectionShell } from "./section-shell.tsx"

interface BrandSectionProps {
  id?: string
  heading?: string
  items: CompanyBrand[]
}

export function BrandSection({ id, heading, items }: BrandSectionProps) {
  const text = useContentText()

  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading}>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((brand) => {
          const name = text(brand.name)

          const card = (
            <>
              {brand.logo_url ? (
                <img
                  src={brand.logo_url}
                  alt={name ?? ""}
                  loading="lazy"
                  // Logo brand datang dengan warna yang berbeda-beda; disamakan
                  // jadi abu lalu berwarna saat disentuh.
                  className="h-8 w-auto opacity-70 grayscale transition-all duration-300 group-hover:opacity-100 group-hover:grayscale-0"
                />
              ) : null}
              <div className="space-y-1">
                <p className="text-sm font-semibold tracking-tight">{name}</p>
                {text(brand.description) ? (
                  <p className="text-sm leading-relaxed text-muted-foreground">
                    {text(brand.description)}
                  </p>
                ) : null}
              </div>
            </>
          )

          return brand.external_url ? (
            <a
              key={brand.id}
              href={brand.external_url}
              target="_blank"
              rel="noreferrer noopener"
              className="group flex flex-col gap-3 rounded-lg border border-border/50 bg-background p-5 transition-all hover:border-border hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
              {card}
            </a>
          ) : (
            <div
              key={brand.id}
              className="group flex flex-col gap-3 rounded-lg border border-border/50 bg-background p-5"
            >
              {card}
            </div>
          )
        })}
      </div>
    </SectionShell>
  )
}

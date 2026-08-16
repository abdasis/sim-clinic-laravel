import type { CompanyBrand } from "#/hooks/use-company-profile.ts"
import { useContentText } from "./locale-context.tsx"
import { SectionShell, type SectionTone } from "./section-shell.tsx"

interface BrandSectionProps {
  id?: string
  eyebrow?: string
  heading?: string
  tone?: SectionTone
  items: CompanyBrand[]
  className?: string
}

export function BrandSection({
  id,
  eyebrow,
  heading,
  tone,
  items,
  className,
}: BrandSectionProps) {
  const text = useContentText()

  if (items.length === 0) return null

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      tone={tone}
      className={className}
    >
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
                  //
                  // `self-start` dan `object-contain` wajib: kartunya kolom
                  // flex, jadi tanpa itu logo diregangkan selebar kartu dan
                  // dipendekkan jadi 32px -- logo 200x80 tampil 315x32,
                  // gepeng dan tidak terbaca lagi sebagai logo.
                  className="h-8 w-auto max-w-[10rem] self-start object-contain opacity-70 grayscale transition-all duration-300 group-hover:opacity-100 group-hover:grayscale-0"
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
              className="group flex flex-col gap-3.5 rounded-xl border border-border/60 bg-background p-6 transition-all duration-200 hover:border-border hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
              {card}
            </a>
          ) : (
            <div
              key={brand.id}
              className="group flex flex-col gap-3.5 rounded-xl border border-border/60 bg-background p-6"
            >
              {card}
            </div>
          )
        })}
      </div>
    </SectionShell>
  )
}

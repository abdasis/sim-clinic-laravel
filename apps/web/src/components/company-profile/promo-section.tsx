import type { CompanyPromo } from "#/hooks/use-company-profile.ts"
import { renderRichText } from "#/lib/tiptap-render.tsx"
import { cn } from "#/lib/utils.ts"
import { CtaLink } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"
import { SectionShell, type SectionTone } from "./section-shell.tsx"

interface PromoSectionProps {
  id?: string
  tenant: string
  eyebrow?: string
  heading?: string
  description?: string
  tone?: SectionTone
  items: CompanyPromo[]
  className?: string
}

export function PromoSection({
  id,
  tenant,
  eyebrow,
  heading,
  description,
  tone,
  items,
  className,
}: PromoSectionProps) {
  if (items.length === 0) return null

  // Promo pertama dibesarkan. Klinik biasanya hanya menjalankan satu promo,
  // dan satu kartu kecil di kisi dua kolom terbaca seperti sisa layout yang
  // gagal terisi — bukan sebagai penawaran yang sedang ditonjolkan.
  const [lead, ...rest] = items

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      description={description}
      tone={tone}
      className={className}
    >
      <div className="space-y-4">
        <PromoCard tenant={tenant} promo={lead} featured />

        {rest.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2">
            {rest.map((promo) => (
              <PromoCard key={promo.id} tenant={tenant} promo={promo} />
            ))}
          </div>
        ) : null}
      </div>
    </SectionShell>
  )
}

function PromoCard({
  tenant,
  promo,
  featured,
}: {
  tenant: string
  promo: CompanyPromo
  featured?: boolean
}) {
  const text = useContentText()
  const title = text(promo.title)

  return (
    <article
      className={cn(
        "group/promo grid overflow-hidden rounded-xl border border-border/60 bg-background transition-[border-color,box-shadow] duration-200 hover:border-border hover:shadow-sm",
        featured ? "md:grid-cols-[1.1fr_1fr]" : "grid-cols-1",
      )}
    >
      {promo.image_url ? (
        <div
          className={cn(
            "relative overflow-hidden bg-muted",
            featured ? "aspect-[16/10] md:aspect-auto md:min-h-[19rem]" : "aspect-[16/9]",
          )}
        >
          <img
            src={promo.image_url}
            alt={title ?? ""}
            loading="lazy"
            className="size-full object-cover ring-1 ring-black/10 transition-transform duration-500 group-hover/promo:scale-[1.03] dark:ring-white/10"
          />
        </div>
      ) : null}

      <div
        className={cn(
          "flex flex-col justify-center gap-3",
          featured ? "p-7 sm:p-9" : "p-5",
        )}
      >
        {title ? (
          <h3
            className={cn(
              "font-semibold tracking-tight text-balance",
              featured ? "text-xl sm:text-2xl" : "text-base",
            )}
          >
            {title}
          </h3>
        ) : null}
        <div className="prose prose-sm max-w-none text-muted-foreground dark:prose-invert">
          {renderRichText(text(promo.description))}
        </div>
        <CtaLink
          tenant={tenant}
          label={promo.cta_label}
          type={promo.cta_type}
          url={promo.cta_url}
          variant={featured ? "default" : "outline"}
          size="sm"
          className="mt-2 self-start"
        />
      </div>
    </article>
  )
}

import type { CompanyPromo } from "#/hooks/use-company-profile.ts"
import { renderRichText } from "#/lib/tiptap-render.tsx"
import { CtaLink } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"
import { SectionShell } from "./section-shell.tsx"

interface PromoSectionProps {
  id?: string
  tenant: string
  heading?: string
  items: CompanyPromo[]
  className?: string
}

export function PromoSection({
  id,
  tenant,
  heading,
  items,
  className,
}: PromoSectionProps) {
  const text = useContentText()

  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading} className={className}>
      <div className="grid gap-4 lg:grid-cols-2">
        {items.map((promo) => {
          const title = text(promo.title)

          return (
            <article
              key={promo.id}
              className="flex gap-4 rounded-lg border border-border/50 bg-background p-4 transition-colors hover:border-border"
            >
              {promo.image_url ? (
                <img
                  src={promo.image_url}
                  alt={title ?? ""}
                  loading="lazy"
                  className="size-24 shrink-0 rounded-md object-cover"
                />
              ) : null}
              <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <h3 className="text-sm font-semibold tracking-tight">{title}</h3>
                <div className="prose prose-sm max-w-none text-muted-foreground dark:prose-invert">
                  {renderRichText(text(promo.description))}
                </div>
                <CtaLink
                  tenant={tenant}
                  label={promo.cta_label}
                  type={promo.cta_type}
                  url={promo.cta_url}
                  variant="outline"
                  size="sm"
                  className="mt-2 self-start"
                />
              </div>
            </article>
          )
        })}
      </div>
    </SectionShell>
  )
}

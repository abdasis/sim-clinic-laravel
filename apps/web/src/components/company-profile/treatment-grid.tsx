import { Link } from "@tanstack/react-router"

import { Badge } from "#/components/ui/badge.tsx"
import type { CompanyTreatment } from "#/hooks/use-company-profile.ts"
import { useContentLocale, useContentText } from "./locale-context.tsx"
import { SectionShell } from "./section-shell.tsx"

interface TreatmentGridProps {
  id?: string
  tenant: string
  heading?: string
  readMoreLabel: string
  items: CompanyTreatment[]
}

export function TreatmentGrid({
  id,
  tenant,
  heading,
  readMoreLabel,
  items,
}: TreatmentGridProps) {
  const text = useContentText()
  const locale = useContentLocale()

  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading}>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((treatment) => {
          const title = text(treatment.title)

          return (
            <Link
              key={treatment.id}
              to="/$tenant/treatment/$slug"
              params={{ tenant, slug: treatment.slug }}
              search={{ lang: locale }}
              className="group flex flex-col overflow-hidden rounded-lg border border-border/50 bg-background transition-all hover:border-border hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
              <div className="relative aspect-[4/3] overflow-hidden bg-muted">
                {treatment.image_url ? (
                  <img
                    src={treatment.image_url}
                    alt={title ?? ""}
                    loading="lazy"
                    className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                  />
                ) : null}
                {treatment.badge_label ? (
                  <Badge className="absolute top-3 left-3" variant="secondary">
                    {treatment.badge_label}
                  </Badge>
                ) : null}
              </div>
              <div className="flex flex-1 flex-col gap-1.5 p-4">
                <h3 className="text-sm font-semibold tracking-tight">{title}</h3>
                {text(treatment.description) ? (
                  <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                    {text(treatment.description)}
                  </p>
                ) : null}
                <div className="mt-auto flex items-center justify-between gap-2 pt-3">
                  <div className="flex flex-wrap gap-1">
                    {treatment.category_tags.slice(0, 2).map((tag) => (
                      <span
                        key={tag}
                        className="rounded-sm bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                  <span className="shrink-0 text-xs font-medium text-muted-foreground transition-colors group-hover:text-foreground">
                    {readMoreLabel} →
                  </span>
                </div>
              </div>
            </Link>
          )
        })}
      </div>
    </SectionShell>
  )
}

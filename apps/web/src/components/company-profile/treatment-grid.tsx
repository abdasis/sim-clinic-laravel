import { Link } from "@tanstack/react-router"

import { Badge } from "#/components/ui/badge.tsx"
import type { CompanyTreatment } from "#/hooks/use-company-profile.ts"
import type { ContentLocale } from "#/lib/company-locale.ts"
import { SectionShell } from "./section-shell.tsx"

interface TreatmentGridProps {
  id?: string
  tenant: string
  heading?: string
  readMoreLabel: string
  locale: ContentLocale
  items: CompanyTreatment[]
}

export function TreatmentGrid({
  id,
  tenant,
  heading,
  readMoreLabel,
  locale,
  items,
}: TreatmentGridProps) {
  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading}>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((treatment) => (
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
                  alt={treatment.name ?? ""}
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
              <h3 className="text-sm font-semibold tracking-tight">
                {treatment.name}
              </h3>
              {treatment.excerpt ? (
                <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                  {treatment.excerpt}
                </p>
              ) : null}
              <div className="mt-auto flex items-center justify-between pt-3">
                {treatment.price_label ? (
                  <span className="text-sm font-medium tabular-nums">
                    {treatment.price_label}
                  </span>
                ) : (
                  <span />
                )}
                <span className="text-xs font-medium text-muted-foreground transition-colors group-hover:text-foreground">
                  {readMoreLabel} →
                </span>
              </div>
            </div>
          </Link>
        ))}
      </div>
    </SectionShell>
  )
}

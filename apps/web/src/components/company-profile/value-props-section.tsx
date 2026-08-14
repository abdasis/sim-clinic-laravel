import type { CompanyValueProp } from "#/hooks/use-company-profile.ts"
import { useContentText } from "./locale-context.tsx"
import { SectionShell } from "./section-shell.tsx"

interface ValuePropsSectionProps {
  id?: string
  heading?: string
  items: CompanyValueProp[]
}

export function ValuePropsSection({
  id,
  heading,
  items,
}: ValuePropsSectionProps) {
  const text = useContentText()

  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading}>
      <div className="grid gap-px overflow-hidden rounded-lg border border-border/50 bg-border/50 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => (
          <article
            key={item.id}
            className="bg-background p-5 transition-colors hover:bg-muted/40"
          >
            {item.icon ? (
              <span className="mb-3 inline-flex size-8 items-center justify-center rounded-md bg-muted text-xs font-medium text-muted-foreground">
                {item.icon.slice(0, 2).toUpperCase()}
              </span>
            ) : null}
            <h3 className="text-sm font-semibold tracking-tight">
              {text(item.title)}
            </h3>
            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
              {text(item.description)}
            </p>
          </article>
        ))}
      </div>
    </SectionShell>
  )
}

import { Avatar, AvatarFallback, AvatarImage } from "#/components/ui/avatar.tsx"
import type { CompanyTestimonial } from "#/hooks/use-company-profile.ts"
import { renderRichText } from "#/lib/tiptap-render.tsx"
import { useContentText } from "./locale-context.tsx"
import { SectionShell, type SectionTone } from "./section-shell.tsx"

interface TestimonialSectionProps {
  id?: string
  eyebrow?: string
  heading?: string
  tone?: SectionTone
  /** Pola label "Pelanggan Sejak 2022"; `:year` diganti tahun testimoni. */
  sinceTemplate?: string
  items: CompanyTestimonial[]
  className?: string
}

export function TestimonialSection({
  id,
  eyebrow,
  heading,
  tone,
  sinceTemplate,
  items,
  className,
}: TestimonialSectionProps) {
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
        {items.map((testimonial) => (
          <figure
            key={testimonial.id}
            className="flex flex-col gap-4 rounded-xl border border-border/60 bg-background p-6 transition-colors duration-200 hover:border-border"
          >
            <blockquote className="prose prose-sm max-w-none flex-1 text-muted-foreground dark:prose-invert">
              {renderRichText(text(testimonial.quote))}
            </blockquote>
            <figcaption className="flex items-center gap-2.5 border-t border-border/50 pt-3">
              <Avatar className="size-8">
                {testimonial.avatar_url ? (
                  <AvatarImage
                    src={testimonial.avatar_url}
                    alt={testimonial.author_name}
                  />
                ) : null}
                <AvatarFallback className="text-xs">
                  {testimonial.author_name.slice(0, 2).toUpperCase()}
                </AvatarFallback>
              </Avatar>
              <div className="min-w-0">
                <p className="truncate text-sm font-medium">
                  {testimonial.author_name}
                </p>
                {testimonial.since_year ? (
                  <p className="truncate text-xs text-muted-foreground tabular-nums">
                    {(sinceTemplate ?? ":year").replace(
                      ":year",
                      String(testimonial.since_year),
                    )}
                  </p>
                ) : null}
              </div>
            </figcaption>
          </figure>
        ))}
      </div>
    </SectionShell>
  )
}

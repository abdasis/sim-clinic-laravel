import { Star } from "lucide-react"

import { Avatar, AvatarFallback, AvatarImage } from "#/components/ui/avatar.tsx"
import type { CompanyTestimonial } from "#/hooks/use-company-profile.ts"
import { renderRichText } from "#/lib/tiptap-render.tsx"
import { cn } from "#/lib/utils.ts"
import { SectionShell } from "./section-shell.tsx"

interface TestimonialSectionProps {
  id?: string
  heading?: string
  items: CompanyTestimonial[]
}

export function TestimonialSection({
  id,
  heading,
  items,
}: TestimonialSectionProps) {
  if (items.length === 0) return null

  return (
    <SectionShell id={id} title={heading}>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((testimonial) => (
          <figure
            key={testimonial.id}
            className="flex flex-col gap-3 rounded-lg border border-border/50 bg-background p-5"
          >
            {testimonial.rating ? (
              <div
                className="flex items-center gap-0.5"
                aria-label={`${testimonial.rating}/5`}
              >
                {Array.from({ length: 5 }, (_, index) => (
                  <Star
                    key={index}
                    aria-hidden
                    className={cn(
                      "size-3.5",
                      index < (testimonial.rating ?? 0)
                        ? "fill-foreground text-foreground"
                        : "text-muted-foreground/30",
                    )}
                  />
                ))}
              </div>
            ) : null}
            <blockquote className="prose prose-sm max-w-none flex-1 text-muted-foreground dark:prose-invert">
              {renderRichText(testimonial.quote)}
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
                {testimonial.author_role ? (
                  <p className="truncate text-xs text-muted-foreground">
                    {testimonial.author_role}
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

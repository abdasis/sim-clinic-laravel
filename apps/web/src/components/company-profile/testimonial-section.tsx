import { QuoteUpIcon, StarIcon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"

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

// Lima bintang statis — semua testimoni dianggap 5/5.
function StaticStars({ className }: { className?: string }) {
  return (
    <div role="img" aria-label="Rating 5 dari 5" className={className}>
      {Array.from({ length: 5 }, (_, i) => (
        <HugeiconsIcon
          key={i}
          icon={StarIcon}
          fill="currentColor"
          strokeWidth={0}
          className="size-4 text-lagoon-deep"
          aria-hidden="true"
        />
      ))}
    </div>
  )
}

function sinceLabel(
  template: string | undefined,
  year: number | null | undefined,
): string | null {
  if (!year) return null
  return (template ?? ":year").replace(":year", String(year))
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

  const [featured, ...rest] = items
  const featuredSince = sinceLabel(sinceTemplate, featured.since_year)

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      tone={tone}
      className={className}
    >
      {/* Layout featured + grid: kartu pertama menonjol di kiri (lebar 2x),
          sisanya grid kartu kecil di kanan. Mobile semua satu kolom. */}
      <div className="flex flex-col gap-5 lg:flex-row">
        {/* Kartu featured — island-shell, quote mark raksasa, bintang, footer. */}
        <figure
          className="island-shell rise-in relative flex flex-col rounded-3xl p-8 sm:p-10 lg:flex-[2]"
          style={{ animationDelay: "0ms" }}
        >
          <HugeiconsIcon
            icon={QuoteUpIcon}
            aria-hidden="true"
            strokeWidth={1}
            className="pointer-events-none absolute top-5 left-6 size-16 text-lagoon/20 sm:size-20"
          />

          <div className="relative flex flex-1 flex-col gap-5 pt-8">
            <blockquote className="prose max-w-none flex-1 text-pretty text-foreground/80 dark:prose-invert">
              {renderRichText(text(featured.quote))}
            </blockquote>

            <StaticStars className="flex gap-0.5" />

            <figcaption className="flex items-center gap-3">
              <Avatar className="size-12 ring-1 ring-black/10 dark:ring-white/10">
                {featured.avatar_url ? (
                  <AvatarImage
                    src={featured.avatar_url}
                    alt={featured.author_name}
                  />
                ) : null}
                <AvatarFallback className="bg-muted text-sm font-medium text-muted-foreground">
                  {featured.author_name.slice(0, 2).toUpperCase()}
                </AvatarFallback>
              </Avatar>
              <div>
                <p className="text-sm font-semibold text-foreground">
                  {featured.author_name}
                </p>
                {featuredSince ? (
                  <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                    {featuredSince}
                  </p>
                ) : null}
              </div>
            </figcaption>
          </div>
        </figure>

        {/* Grid kartu kecil — polos, border tipis, hover lembut. */}
        {rest.length > 0 ? (
          <div className="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-2">
            {rest.map((testimonial, index) => {
              const since = sinceLabel(sinceTemplate, testimonial.since_year)
              return (
                <figure
                  key={testimonial.id}
                  className="rise-in flex flex-col gap-4 rounded-2xl border border-border/50 bg-muted/30 p-5 transition-transform duration-200 hover:-translate-y-0.5 sm:p-6"
                  style={{ animationDelay: `${(index + 1) * 80}ms` }}
                >
                  <StaticStars className="flex gap-0.5" />
                  <blockquote className="prose prose-sm max-w-none flex-1 text-pretty text-foreground/75 dark:prose-invert">
                    {renderRichText(text(testimonial.quote))}
                  </blockquote>
                  <figcaption className="flex items-center gap-3">
                    <Avatar className="size-10 ring-1 ring-black/10 dark:ring-white/10">
                      {testimonial.avatar_url ? (
                        <AvatarImage
                          src={testimonial.avatar_url}
                          alt={testimonial.author_name}
                        />
                      ) : null}
                      <AvatarFallback className="bg-background text-xs font-medium text-muted-foreground">
                        {testimonial.author_name.slice(0, 2).toUpperCase()}
                      </AvatarFallback>
                    </Avatar>
                    <div>
                      <p className="text-sm font-semibold text-foreground">
                        {testimonial.author_name}
                      </p>
                      {since ? (
                        <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                          {since}
                        </p>
                      ) : null}
                    </div>
                  </figcaption>
                </figure>
              )
            })}
          </div>
        ) : null}
      </div>
    </SectionShell>
  )
}
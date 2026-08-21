import { QuoteUpIcon, StarIcon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"

import { Avatar, AvatarFallback, AvatarImage } from "#/components/ui/avatar.tsx"
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from "#/components/ui/carousel.tsx"
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

const SLIDE_BASIS = "basis-full sm:basis-1/2 lg:basis-1/3"

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

  // Satu testimoni tidak butuh slider — kartu tampil polos tanpa nav panah.
  if (items.length === 1) {
    return (
      <SectionShell
        id={id}
        eyebrow={eyebrow}
        title={heading}
        tone={tone}
        className={className}
      >
        <div className="mx-auto max-w-xl">
          <TestimonialCard
            testimonial={items[0]}
            since={sinceLabel(sinceTemplate, items[0].since_year)}
            quote={renderRichText(text(items[0].quote))}
          />
        </div>
      </SectionShell>
    )
  }

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      tone={tone}
      className={className}
    >
      {/* Slider embla: satu kartu per slide, snap per-gesek, loop. Panah
          nav diletakkan di luar viewport kartu supaya tidak menutupi teks. */}
      <Carousel opts={{ loop: true, align: "start" }} className="rise-in px-2">
        <CarouselContent>
          {items.map((testimonial) => (
            <CarouselItem key={testimonial.id} className={SLIDE_BASIS}>
              <TestimonialCard
                testimonial={testimonial}
                since={sinceLabel(sinceTemplate, testimonial.since_year)}
                quote={renderRichText(text(testimonial.quote))}
              />
            </CarouselItem>
          ))}
        </CarouselContent>

        <CarouselPrevious
          variant="outline"
          className="left-0 size-9 rounded-full shadow-sm transition-transform duration-150 active:scale-[0.96] sm:-left-12"
        />
        <CarouselNext
          variant="outline"
          className="right-0 size-9 rounded-full shadow-sm transition-transform duration-150 active:scale-[0.96] sm:-right-12"
        />
      </Carousel>
    </SectionShell>
  )
}

/**
 * Kartu testimoni gaya Linear: ring-1 ring-foreground/10 (bukan border
 * tinted), rounded-2xl, latar bg-card. Tanda kutip besar sebagai jangkar
 * visual di sudut. Radius concentric — kartu rounded-2xl, avatar rounded-full.
 */
function TestimonialCard({
  testimonial,
  since,
  quote,
}: {
  testimonial: CompanyTestimonial
  since: string | null
  quote: React.ReactNode
}) {
  return (
    <figure className="relative flex h-full flex-col gap-5 rounded-2xl bg-card p-7 ring-1 ring-foreground/10 shadow-sm sm:p-8">
      <HugeiconsIcon
        icon={QuoteUpIcon}
        aria-hidden="true"
        strokeWidth={1}
        className="pointer-events-none absolute top-5 left-5 size-12 text-lagoon/20"
      />

      <div className="relative flex flex-1 flex-col gap-4 pt-6">
        <blockquote className="prose prose-sm max-w-none flex-1 text-pretty text-foreground/80 dark:prose-invert">
          {quote}
        </blockquote>

        <StaticStars className="flex gap-0.5" />

        <figcaption className="flex items-center gap-3">
          <Avatar className="size-11 ring-1 ring-black/10 dark:ring-white/10">
            {testimonial.avatar_url ? (
              <AvatarImage
                src={testimonial.avatar_url}
                alt={testimonial.author_name}
              />
            ) : null}
            <AvatarFallback className="bg-muted text-xs font-medium text-muted-foreground">
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
      </div>
    </figure>
  )
}
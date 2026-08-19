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
      {/* Kolom yang mengalir, bukan grid kaku: jumlah testimoni tidak pernah
          pas dibagi tiga, dan sisa satu kartu di baris terakhir meninggalkan
          lubang selebar dua kartu. */}
      <div className="columns-1 gap-5 sm:columns-2 lg:columns-3 [&>*]:mb-5 [&>*]:break-inside-avoid">
        {items.map((testimonial) => (
          <figure
            key={testimonial.id}
            // Latar merah muda lembut, bukan kartu putih bergaris. Blok
            // testimoni memang bagian yang boleh terasa hangat — di sinilah
            // halaman berhenti berbicara sebagai klinik dan mulai berbicara
            // sebagai orang.
            className="flex flex-col items-center gap-4 rounded-2xl bg-blush/45 p-7 text-center transition-transform duration-200 hover:-translate-y-0.5"
          >
            <Avatar className="size-14 ring-4 ring-background/70">
              {testimonial.avatar_url ? (
                <AvatarImage
                  src={testimonial.avatar_url}
                  alt={testimonial.author_name}
                />
              ) : null}
              <AvatarFallback className="bg-background/80 text-sm font-medium text-blush-ink">
                {testimonial.author_name.slice(0, 2).toUpperCase()}
              </AvatarFallback>
            </Avatar>

            <blockquote className="prose prose-sm max-w-none flex-1 text-pretty text-foreground/75 dark:prose-invert">
              {renderRichText(text(testimonial.quote))}
            </blockquote>

            <figcaption>
              <p className="text-sm font-semibold">{testimonial.author_name}</p>
              {testimonial.since_year ? (
                <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                  {(sinceTemplate ?? ":year").replace(
                    ":year",
                    String(testimonial.since_year),
                  )}
                </p>
              ) : null}
            </figcaption>
          </figure>
        ))}
      </div>
    </SectionShell>
  )
}

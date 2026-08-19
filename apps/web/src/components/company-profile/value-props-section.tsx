import { HugeiconsIcon } from "@hugeicons/react"

import type { CompanyValueProp } from "#/hooks/use-company-profile.ts"
import { useContentText } from "./locale-context.tsx"
import { SectionShell, type SectionTone } from "./section-shell.tsx"
import { valuePropIcon } from "./value-prop-icons.ts"

interface ValuePropsSectionProps {
  id?: string
  eyebrow?: string
  heading?: string
  description?: string
  tone?: SectionTone
  items: CompanyValueProp[]
  className?: string
}

export function ValuePropsSection({
  id,
  eyebrow,
  heading,
  description,
  tone,
  items,
  className,
}: ValuePropsSectionProps) {
  const text = useContentText()

  if (items.length === 0) return null

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      description={description}
      tone={tone}
      className={className}
    >
      {/* Kisi berjarak satu piksel: garis pemisahnya berasal dari latar yang
          menembus celah, jadi tidak ada border ganda di pertemuan kartu. */}
      <div className="grid gap-px overflow-hidden rounded-xl border border-border/60 bg-border/60 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => (
          <article
            key={item.id}
            className="group/prop relative bg-background p-6 transition-colors duration-200 hover:bg-muted/50"
          >
            {/* Lencana ikon memakai warna merek, bukan abu-abu: enam kotak
                netral berjajar terbaca sebagai daftar, dan tidak ada satu pun
                yang menahan pandangan. */}
            <span className="mb-4 inline-flex size-10 items-center justify-center rounded-lg bg-primary/8 text-primary ring-1 ring-primary/10 transition-all duration-200 group-hover/prop:scale-105 group-hover/prop:bg-primary/12">
              <HugeiconsIcon
                icon={valuePropIcon(item.icon)}
                strokeWidth={1.8}
                className="size-5"
              />
            </span>
            <h3 className="text-sm font-semibold tracking-tight">
              {text(item.title)}
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground text-pretty">
              {text(item.description)}
            </p>
          </article>
        ))}
      </div>
    </SectionShell>
  )
}

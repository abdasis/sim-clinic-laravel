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
      {/* Daftar berikon besar, bukan kotak berbingkai. Enam kartu bergaris
          terbaca sebagai tabel keunggulan yang harus dibaca satu-satu;
          ikon besar tanpa bingkai membuat tiap butir berdiri sendiri dan
          matanya bebas melompat ke yang menarik perhatiannya. */}
      <div className="mx-auto grid max-w-5xl gap-x-12 gap-y-9 sm:grid-cols-2">
        {items.map((item) => (
          <article key={item.id} className="group/prop flex gap-5">
            <span className="shrink-0 text-primary transition-transform duration-200 group-hover/prop:scale-105">
              <HugeiconsIcon
                icon={valuePropIcon(item.icon)}
                strokeWidth={1.4}
                className="size-11"
              />
            </span>
            <div className="min-w-0 pt-1">
              <h3 className="text-base font-semibold tracking-tight text-primary">
                {text(item.title)}
              </h3>
              <p className="mt-1.5 text-sm leading-relaxed text-pretty text-muted-foreground">
                {text(item.description)}
              </p>
            </div>
          </article>
        ))}
      </div>
    </SectionShell>
  )
}

import { ArrowRight01Icon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"
import { Link } from "@tanstack/react-router"

import type { CompanyTreatment } from "#/hooks/use-company-profile.ts"
import { useContentLocale, useContentText } from "./locale-context.tsx"
import { SectionShell, type SectionTone } from "./section-shell.tsx"

const CARD_CLASS =
  "group flex flex-col overflow-hidden rounded-2xl border border-border/60 bg-card transition-[transform,border-color,box-shadow] duration-200 hover:-translate-y-1 hover:border-primary/25 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"

interface TreatmentGridProps {
  id?: string
  tenant: string
  eyebrow?: string
  heading?: string
  description?: string
  tone?: SectionTone
  action?: React.ReactNode
  readMoreLabel: string
  items: CompanyTreatment[]
  /**
   * Diisi saat grid dipakai di halaman marketing, yang detail treatment-nya
   * ada di akar situs. Tanpa ini, tautan tetap ke halaman tenant.
   */
  basePath?: "/treatment"
  className?: string
}

export function TreatmentGrid({
  id,
  tenant,
  eyebrow,
  heading,
  description,
  tone,
  action,
  readMoreLabel,
  items,
  basePath,
  className,
}: TreatmentGridProps) {
  const locale = useContentLocale()

  if (items.length === 0) return null

  return (
    <SectionShell
      id={id}
      eyebrow={eyebrow}
      title={heading}
      description={description}
      tone={tone}
      action={action}
      className={className}
    >
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((treatment) =>
          // Dua varian Link karena `to` harus berupa literal agar tipenya
          // terperiksa; isi kartunya sendiri sama persis.
          basePath ? (
            <Link
              key={treatment.id}
              to="/treatment/$slug"
              params={{ slug: treatment.slug }}
              search={{ lang: locale }}
              className={CARD_CLASS}
            >
              <TreatmentCard treatment={treatment} readMoreLabel={readMoreLabel} />
            </Link>
          ) : (
            <Link
              key={treatment.id}
              to="/$tenant/treatment/$slug"
              params={{ tenant, slug: treatment.slug }}
              search={{ lang: locale }}
              className={CARD_CLASS}
            >
              <TreatmentCard treatment={treatment} readMoreLabel={readMoreLabel} />
            </Link>
          ),
        )}
      </div>
    </SectionShell>
  )
}

function TreatmentCard({
  treatment,
  readMoreLabel,
}: {
  treatment: CompanyTreatment
  readMoreLabel: string
}) {
  const text = useContentText()
  const title = text(treatment.title)
  const description = text(treatment.description)

  return (
    <>
      <div className="relative aspect-[4/3] overflow-hidden bg-muted">
        {treatment.image_url ? (
          <img
            src={treatment.image_url}
            alt={title ?? ""}
            loading="lazy"
            className="size-full object-cover ring-1 ring-black/10 transition-transform duration-500 group-hover:scale-[1.03] dark:ring-white/10"
          />
        ) : null}
        {treatment.badge_label ? (
          // Pita bertakik, bukan lencana kotak: bentuknya menempel di tepi
          // gambar dan terbaca sebagai label yang ditempelkan klinik, bukan
          // elemen antarmuka yang mengambang di atas foto.
          <span
            className="absolute top-4 left-0 bg-primary py-1.5 pr-5 pl-4 text-2xs font-semibold tracking-[0.08em] text-primary-foreground uppercase shadow-sm"
            style={{
              clipPath: "polygon(0 0, 100% 0, calc(100% - 0.55rem) 50%, 100% 100%, 0 100%)",
            }}
          >
            {treatment.badge_label}
          </span>
        ) : null}
      </div>
      <div className="flex flex-1 flex-col gap-1.5 p-5">
        <h3 className="text-base leading-snug font-semibold tracking-tight text-balance">
          {title}
        </h3>
        {description ? (
          <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
            {description}
          </p>
        ) : null}
        <div className="mt-auto flex items-center justify-between gap-2 pt-3">
          <div className="flex flex-wrap gap-1">
            {treatment.category_tags.slice(0, 2).map((tag) => (
              <span
                key={tag}
                className="rounded-full bg-primary/8 px-2.5 py-1 text-2xs font-medium text-primary"
              >
                {tag}
              </span>
            ))}
          </div>
          {/* Panah ikut bergerak saat kartunya disentuh — isyarat kecil bahwa
              kartunya bisa diklik, bukan sekadar kotak berisi teks. */}
          <span className="flex shrink-0 items-center gap-1 text-xs font-medium text-muted-foreground transition-colors group-hover:text-foreground">
            {readMoreLabel}
            <HugeiconsIcon
              icon={ArrowRight01Icon}
              strokeWidth={2}
              className="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5"
            />
          </span>
        </div>
      </div>
    </>
  )
}

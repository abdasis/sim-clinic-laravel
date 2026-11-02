import type { ReactNode } from "react"

import { LANDING_CONTENT } from "#/lib/landing-content.ts"

export interface LegalSection {
  id: string
  heading: string
  body: ReactNode
}

export interface LegalContact {
  name: string
  address: string
  phone: string
  whatsappUrl: string
}

/** Kontak resmi klinik, diambil dari satu sumber: konten landing. */
export function legalContact(): LegalContact {
  const { settings } = LANDING_CONTENT

  return {
    name: settings?.site_name?.id ?? "Meba Clinic Aesthetic",
    address: settings?.address ?? "",
    phone: settings?.phone ?? "",
    whatsappUrl:
      settings?.chat_channels.find((channel) => channel.type === "whatsapp")
        ?.url ?? "",
  }
}

interface LegalDocumentProps {
  kicker: string
  title: string
  summary: string
  effectiveDate: string
  sections: LegalSection[]
}

/**
 * Bingkai halaman legal: judul, tanggal berlaku, daftar isi, lalu bagiannya.
 * Satu kolom baca (±720px) supaya paragraf panjang tetap nyaman.
 *
 * Isi halaman sengaja hanya Bahasa Indonesia; versi Inggris menyusul bila
 * dibutuhkan. Label navigasi tetap dua bahasa lewat `Translatable`.
 */
export function LegalDocument({
  kicker,
  title,
  summary,
  effectiveDate,
  sections,
}: LegalDocumentProps) {
  return (
    <main className="mx-auto w-full max-w-[45rem] px-4 py-12 sm:py-16">
      <header>
        <p className="island-kicker mb-2">{kicker}</p>
        <h1 className="display-title text-3xl font-bold tracking-tight text-sea-ink text-balance sm:text-4xl">
          {title}
        </h1>
        <p className="mt-3 text-sm leading-7 text-sea-ink-soft text-pretty">
          {summary}
        </p>
        <p className="mt-4 text-xs text-muted-foreground tabular-nums">
          Berlaku sejak {effectiveDate}
        </p>
      </header>

      <nav
        aria-label="Daftar isi"
        className="mt-8 rounded-lg border border-border/50 bg-muted/30 p-4"
      >
        <p className="mb-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
          Daftar isi
        </p>
        <ol className="grid gap-1 sm:grid-cols-2 sm:gap-x-6">
          {sections.map((section, index) => (
            <li key={section.id}>
              <a
                href={`#${section.id}`}
                className="block rounded-sm py-0.5 text-sm text-sea-ink-soft no-underline transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
              >
                <span className="mr-2 text-muted-foreground tabular-nums">
                  {index + 1}.
                </span>
                {section.heading}
              </a>
            </li>
          ))}
        </ol>
      </nav>

      <div className="mt-10 space-y-9">
        {sections.map((section) => (
          <section key={section.id} aria-labelledby={section.id}>
            <h2
              id={section.id}
              className="scroll-mt-28 text-base font-semibold tracking-tight text-sea-ink"
            >
              {section.heading}
            </h2>
            <div className="mt-2 space-y-3 text-sm leading-7 text-sea-ink-soft text-pretty">
              {section.body}
            </div>
          </section>
        ))}
      </div>
    </main>
  )
}

/** Tautan keluar; gaya sama dengan tautan isi di halaman marketing lain. */
export function LegalLink({
  href,
  children,
}: {
  href: string
  children: ReactNode
}) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noreferrer noopener"
      className="rounded-sm font-medium text-sea-ink underline decoration-border underline-offset-4 transition-colors hover:decoration-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
    >
      {children}
    </a>
  )
}

/** Daftar butir di dalam bagian; penanda tetap di luar paragraf. */
export function LegalList({ items }: { items: string[] }) {
  return (
    <ul className="list-disc space-y-1.5 pl-5">
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ul>
  )
}

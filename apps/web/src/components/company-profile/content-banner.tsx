import type { CompanyContentSection } from "#/hooks/use-company-profile.ts"
import { renderRichText } from "#/lib/tiptap-render.tsx"
import { cn } from "#/lib/utils.ts"
import { CtaLink } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"

interface ContentBannerProps {
  id?: string
  tenant: string
  section?: CompanyContentSection | null
  /** Beri penekanan lebih untuk section ajakan (booking, e-store). */
  emphasis?: boolean
  className?: string
}

/**
 * Section konten bebas. Admin memilih tata letaknya: banner selebar halaman
 * atau dua kolom gambar-teks.
 *
 * Varian banner memakai gambarnya sebagai latar, bukan menaruhnya sebagai
 * potongan terpisah di bawah teks. Sebelumnya kotak banner nyaris kosong —
 * teks di pojok kiri atas, tombol di pojok kanan, dan sisanya ruang menganggur
 * setinggi hampir dua ratus piksel.
 */
export function ContentBanner({
  id,
  tenant,
  section,
  emphasis,
  className,
}: ContentBannerProps) {
  const text = useContentText()

  if (!section) return null

  const isSplit = section.layout_type === "split"
  const title = text(section.title)

  return (
    <section
      id={id ?? section.section_key}
      className={cn(
        "scroll-mt-16 border-t border-border/40",
        isSplit ? "py-16 sm:py-20" : "",
        className,
      )}
    >
      <div className={cn(isSplit ? "mx-auto w-full max-w-6xl px-4 sm:px-6" : "")}>
        {isSplit ? (
          <div className="grid items-stretch overflow-hidden rounded-xl border border-border/60 bg-background md:grid-cols-2">
            {section.image_url ? (
              <div className="relative min-h-56 overflow-hidden bg-muted md:order-last">
                <img
                  src={section.image_url}
                  alt={title ?? ""}
                  loading="lazy"
                  className="absolute inset-0 size-full object-cover ring-1 ring-black/10 dark:ring-white/10"
                />
              </div>
            ) : null}

            <div className="flex flex-col justify-center gap-4 p-7 sm:p-10">
              {title ? (
                <h2 className="text-xl font-semibold tracking-tight text-balance sm:text-2xl">
                  {title}
                </h2>
              ) : null}
              <div className="prose prose-sm max-w-none text-muted-foreground dark:prose-invert">
                {renderRichText(text(section.body))}
              </div>
              <CtaLink
                tenant={tenant}
                label={section.cta_label}
                type={section.cta_type}
                url={section.cta_url}
                variant={emphasis ? "default" : "outline"}
                className="mt-1 self-start active:scale-[0.96]"
              />
            </div>
          </div>
        ) : null}
      </div>

      {!isSplit ? (
        // Selebar layar, bukan kartu di dalam kolom. Ajakan yang paling ingin
        // diklik tidak seharusnya duduk di dalam bingkai yang sama dengan
        // daftar-daftar di atasnya — bidang penuh membuat mata berhenti.
        <div className="relative isolate overflow-hidden">
          {section.image_url ? (
            <img
              src={section.image_url}
              alt=""
              aria-hidden="true"
              loading="lazy"
              className="absolute inset-0 size-full object-cover"
            />
          ) : null}

          {/* Lapisan warna merek, bukan hitam. Foto di baliknya tetap terbaca
              sebagai suasana klinik, sementara warnanya yang memberi tahu
              bagian ini milik siapa. */}
          <div
            className={cn(
              "absolute inset-0",
              emphasis ? "bg-primary/88" : "bg-blush/85",
            )}
          />

          <div className="relative mx-auto flex w-full max-w-3xl flex-col items-center gap-5 px-4 py-16 text-center sm:px-6 sm:py-20">
            {title ? (
              <h2
                className={cn(
                  "text-lg font-semibold tracking-[0.14em] text-balance uppercase sm:text-2xl",
                  emphasis ? "text-primary-foreground" : "text-blush-ink",
                )}
              >
                {title}
              </h2>
            ) : null}

            <div
              className={cn(
                "prose prose-sm max-w-xl text-pretty dark:prose-invert",
                emphasis
                  ? "text-primary-foreground/85 prose-p:text-primary-foreground/85"
                  : "text-blush-ink/80 prose-p:text-blush-ink/80",
              )}
            >
              {renderRichText(text(section.body))}
            </div>

            <CtaLink
              tenant={tenant}
              label={section.cta_label}
              type={section.cta_type}
              url={section.cta_url}
              size="lg"
              variant="secondary"
              className="mt-1 rounded-full bg-background px-8 text-foreground shadow-sm transition-transform duration-200 hover:-translate-y-0.5 hover:bg-background active:scale-[0.96]"
            />
          </div>
        </div>
      ) : null}
    </section>
  )
}

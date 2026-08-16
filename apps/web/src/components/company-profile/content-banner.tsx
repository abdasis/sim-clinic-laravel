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
        "scroll-mt-16 border-t border-border/40 py-16 sm:py-20",
        className,
      )}
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
        {isSplit ? (
          <div className="grid items-stretch overflow-hidden rounded-xl border border-border/60 bg-background md:grid-cols-2">
            {section.image_url ? (
              <div className="relative min-h-56 overflow-hidden bg-muted md:order-last">
                <img
                  src={section.image_url}
                  alt={title ?? ""}
                  loading="lazy"
                  className="absolute inset-0 size-full object-cover"
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
                className="mt-1 self-start"
              />
            </div>
          </div>
        ) : (
          <div className="relative overflow-hidden rounded-xl border border-border/60">
            {section.image_url ? (
              <>
                <img
                  src={section.image_url}
                  alt=""
                  aria-hidden="true"
                  loading="lazy"
                  className="absolute inset-0 size-full object-cover"
                />
                <div className="absolute inset-0 bg-gradient-to-r from-neutral-950/85 via-neutral-950/65 to-neutral-950/35" />
              </>
            ) : (
              <div
                className={cn(
                  "absolute inset-0",
                  emphasis ? "bg-muted/60" : "bg-muted/30",
                )}
              />
            )}

            <div
              className={cn(
                "relative flex flex-col gap-5 p-8 sm:flex-row sm:items-center sm:justify-between sm:p-12",
                section.image_url ? "text-white" : "",
              )}
            >
              <div className="max-w-2xl space-y-2.5">
                {title ? (
                  <h2 className="text-xl font-semibold tracking-tight text-balance sm:text-2xl">
                    {title}
                  </h2>
                ) : null}
                <div
                  className={cn(
                    "prose prose-sm max-w-none dark:prose-invert",
                    section.image_url
                      ? "text-white/75 prose-p:text-white/75"
                      : "text-muted-foreground",
                  )}
                >
                  {renderRichText(text(section.body))}
                </div>
              </div>
              <CtaLink
                tenant={tenant}
                label={section.cta_label}
                type={section.cta_type}
                url={section.cta_url}
                size="lg"
                variant={section.image_url || emphasis ? "default" : "outline"}
                className="shrink-0 self-start transition-transform duration-200 hover:-translate-y-0.5 sm:self-auto"
              />
            </div>
          </div>
        )}
      </div>
    </section>
  )
}

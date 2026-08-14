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
      className={cn("scroll-mt-20 border-t border-border/50 py-14", className)}
    >
      <div className="mx-auto w-full max-w-6xl px-4">
        <div
          className={cn(
            "overflow-hidden rounded-lg border border-border/50",
            emphasis ? "bg-muted/60" : "bg-background",
            isSplit ? "grid items-stretch gap-0 md:grid-cols-2" : "",
          )}
        >
          {isSplit && section.image_url ? (
            <img
              src={section.image_url}
              alt={title ?? ""}
              loading="lazy"
              className="h-full min-h-52 w-full object-cover"
            />
          ) : null}

          <div
            className={cn(
              "flex flex-col justify-center gap-3 p-6",
              isSplit ? "" : "sm:flex-row sm:items-center sm:justify-between",
            )}
          >
            <div className="max-w-2xl space-y-2">
              {title ? (
                <h2 className="text-xl font-semibold tracking-tight text-balance">
                  {title}
                </h2>
              ) : null}
              <div className="prose prose-sm max-w-none text-muted-foreground dark:prose-invert">
                {renderRichText(text(section.body))}
              </div>
            </div>
            <CtaLink
              tenant={tenant}
              label={section.cta_label}
              type={section.cta_type}
              url={section.cta_url}
              variant={emphasis ? "default" : "outline"}
              className="shrink-0 self-start"
            />
          </div>

          {!isSplit && section.image_url ? (
            <img
              src={section.image_url}
              alt={title ?? ""}
              loading="lazy"
              className="h-44 w-full object-cover"
            />
          ) : null}
        </div>
      </div>
    </section>
  )
}

import { createFileRoute, Link, useParams } from "@tanstack/react-router"

import { CtaLink } from "#/components/company-profile/cta-link.tsx"
import { useContentLocale, useContentText } from "#/components/company-profile/locale-context.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { findTreatment } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/treatment/$slug")({
  component: TreatmentDetailPage,
})

const WHATSAPP = "https://wa.me/6281234567890"

function TreatmentDetailPage() {
  const { slug } = useParams({ from: "/_marketing/treatment/$slug" })
  const locale = useContentLocale()
  const text = useContentText()
  const isEnglish = locale === "en"

  const treatment = findTreatment(slug)

  if (!treatment) {
    return (
      <MarketingPage
        crumbs={[
          { label: isEnglish ? "Treatments" : "Treatment", href: "/treatments" },
          { label: isEnglish ? "Not found" : "Tidak ditemukan" },
        ]}
        title={isEnglish ? "Treatment not found" : "Treatment tidak ditemukan"}
        description={
          isEnglish
            ? "The page you are looking for may have been renamed or removed."
            : "Halaman yang Anda cari mungkin sudah berganti nama atau dihapus."
        }
      >
        <Button asChild variant="outline">
          <Link to="/treatments" search={{ lang: locale }}>
            {isEnglish ? "See all treatments" : "Lihat semua treatment"}
          </Link>
        </Button>
      </MarketingPage>
    )
  }

  const title = text(treatment.title) ?? ""

  return (
    <MarketingPage
      crumbs={[
        { label: isEnglish ? "Treatments" : "Treatment", href: "/treatments" },
        { label: title },
      ]}
      title={title}
      description={text(treatment.description)}
    >
      <div className="space-y-6">
        <div className="flex flex-wrap items-center gap-1.5">
          {treatment.badge_label ? (
            <Badge variant="secondary">{treatment.badge_label}</Badge>
          ) : null}
          {treatment.category_tags.map((tag) => (
            <span
              key={tag}
              className="rounded-sm bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
            >
              {tag}
            </span>
          ))}
        </div>

        {treatment.image_url ? (
          <img
            src={treatment.image_url}
            alt={title}
            loading="lazy"
            className="aspect-[16/9] w-full rounded-lg border border-border/50 object-cover"
          />
        ) : null}

        <div className="grid gap-6 rounded-lg border border-border/50 bg-background p-6 sm:grid-cols-3">
          <Detail
            label={isEnglish ? "Duration" : "Durasi"}
            value={isEnglish ? "45–60 minutes" : "45–60 menit"}
          />
          <Detail
            label={isEnglish ? "Downtime" : "Waktu Pulih"}
            value={isEnglish ? "Minimal" : "Minimal"}
          />
          <Detail
            label={isEnglish ? "Consultation" : "Konsultasi"}
            value={isEnglish ? "Free on first visit" : "Gratis kunjungan pertama"}
          />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <CtaLink
            tenant=""
            label={{
              id: "Tanya lewat WhatsApp",
              en: "Ask us on WhatsApp",
            }}
            type="whatsapp"
            url={WHATSAPP}
          />
          <Button asChild variant="outline">
            <Link to="/online-booking" search={{ lang: locale }}>
              {isEnglish ? "Book this treatment" : "Booking treatment ini"}
            </Link>
          </Button>
        </div>
      </div>
    </MarketingPage>
  )
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      <p className="mt-0.5 text-sm font-medium">{value}</p>
    </div>
  )
}

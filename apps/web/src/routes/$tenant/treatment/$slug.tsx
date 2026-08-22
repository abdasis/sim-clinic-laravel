import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  ArrowLeft01Icon,
  Clock01Icon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons"

import { CompanyFooter } from "#/components/company-profile/company-footer.tsx"
import { CompanyHeader } from "#/components/company-profile/company-header.tsx"
import { FaqSection } from "#/components/company-profile/faq-section.tsx"
import { CompanyLocaleProvider } from "#/components/company-profile/locale-context.tsx"
import { TreatmentGrid } from "#/components/company-profile/treatment-grid.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useCompanyTreatment } from "#/hooks/use-company-profile.ts"
import type { CompanySettings } from "#/hooks/use-company-profile.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatCurrency } from "#/lib/format.ts"
import { normalizeLocale, pickTranslatable } from "#/lib/company-locale.ts"

export const Route = createFileRoute("/$tenant/treatment/$slug")({
  component: TreatmentDetailPage,
  validateSearch: (search: Record<string, unknown>) => ({
    lang: search.lang ? String(search.lang) : undefined,
  }),
})

function TreatmentDetailPage() {
  const { tenant, slug } = useParams({ from: "/$tenant/treatment/$slug" })
  const { lang } = Route.useSearch()
  const locale = normalizeLocale(lang)
  const { t } = useTrans(locale)

  const { data, isLoading, isError } = useCompanyTreatment(tenant, slug, locale)

  const treatment = data?.treatment
  const settings = data?.chrome?.settings ?? null
  const title = pickTranslatable(treatment?.title, locale)
  const description = pickTranslatable(treatment?.description, locale)

  return (
    <CompanyLocaleProvider locale={locale}>
      <div className="public-site flex min-h-svh flex-col font-brand">
        <CompanyHeader
          tenant={tenant}
          settings={settings}
          items={data?.chrome?.navigation.header ?? []}
          locale={locale}
          languageLabel={t("company_profile.language")}
          onLocaleChange={() => {}}
        />

        {isLoading ? (
          <main className="mx-auto w-full max-w-6xl flex-1 space-y-6 px-4 py-12 sm:px-6">
            <Skeleton className="h-9 w-72" />
            <Skeleton className="aspect-[16/9] w-full" />
            <Skeleton className="h-32 w-full" />
          </main>
        ) : isError || !treatment ? (
          /*
            Halaman yang dilihat calon pasien, bukan staf: tautan lama dari
            chat atau media sosial tetap diklik lama setelah treatmentnya
            diganti. Satu baris "tidak ada data" di tengah layar kosong
            terbaca seperti situsnya rusak, dan menutup satu-satunya jalan
            yang tersisa — melihat treatment lain yang masih ada.
          */
          <main className="mx-auto flex w-full max-w-lg flex-1 flex-col items-center justify-center gap-5 px-4 py-24 text-center">
            <div className="space-y-2">
              <h1 className="text-xl font-semibold tracking-tight">
                {t("company_profile.treatment_not_found")}
              </h1>
              <p className="text-sm text-pretty text-muted-foreground">
                {t("company_profile.treatment_not_found_desc")}
              </p>
            </div>
            <Button asChild size="sm">
              <Link to="/$tenant" params={{ tenant }} search={{ lang: locale }}>
                <HugeiconsIcon icon={ArrowLeft01Icon} className="size-4" />
                {t("company_profile.see_other_treatments")}
              </Link>
            </Button>
          </main>
        ) : (
          <main className="flex-1">
            <section className="border-b border-border/40 py-10 sm:py-14">
              <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
                <Button
                  asChild
                  variant="ghost"
                  size="sm"
                  className="-ml-2 mb-6 text-muted-foreground"
                >
                  <Link to="/$tenant" params={{ tenant }} search={{ lang: locale }}>
                    <HugeiconsIcon icon={ArrowLeft01Icon} className="size-4" />
                    {t("general.back")}
                  </Link>
                </Button>

                {/* Gambar dan keputusan berdampingan: yang meyakinkan orang
                    memang rupa treatmentnya, tapi yang membuatnya menekan
                    tombol adalah harga dan durasi yang terlihat sekaligus. */}
                <div className="grid gap-8 lg:grid-cols-[1.15fr_1fr] lg:items-start">
                  {treatment.image_url ? (
                    <img
                      src={treatment.image_url}
                      alt={title ?? ""}
                      className="aspect-[4/3] w-full rounded-xl border border-border/50 object-cover"
                    />
                  ) : (
                    <div className="aspect-[4/3] w-full rounded-xl bg-muted" />
                  )}

                  <div className="lg:pt-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                      {treatment.badge_label ? (
                        <Badge variant="secondary">
                          {treatment.badge_label}
                        </Badge>
                      ) : null}
                      {treatment.category_tags.map((tag) => (
                        <span
                          key={tag}
                          className="rounded-full bg-muted px-2 py-0.5 text-2xs text-muted-foreground"
                        >
                          {tag}
                        </span>
                      ))}
                    </div>

                    <h1 className="mt-3 text-2xl leading-tight font-semibold tracking-tight text-balance sm:text-3xl">
                      {title}
                    </h1>

                    {description ? (
                      <p className="mt-3 text-sm leading-relaxed text-pretty text-muted-foreground sm:text-base">
                        {description}
                      </p>
                    ) : null}

                    <PriceBlock
                      treatment={treatment}
                      labels={{
                        from: t("company_profile.price_from"),
                        promo: t("company_profile.promo_price"),
                        minutes: t("company_profile.minutes"),
                      }}
                    />

                    <BookingActions
                      tenant={tenant}
                      settings={settings}
                      title={title}
                      labels={{
                        book: t("company_profile.book_treatment"),
                        ask: t("company_profile.ask_treatment"),
                        template: t("company_profile.whatsapp_template"),
                      }}
                    />
                  </div>
                </div>
              </div>
            </section>

            <FaqSection
              id="faq"
              tone="muted"
              eyebrow={t("company_profile.faq")}
              heading={t("company_profile.faq_treatment_heading")}
              items={treatment.faqs ?? []}
            />

            {data.related.length > 0 ? (
              <TreatmentGrid
                id="lainnya"
                tenant={tenant}
                eyebrow={t("company_profile.treatments")}
                heading={t("company_profile.related_treatments")}
                readMoreLabel={t("company_profile.read_more")}
                items={data.related}
              />
            ) : null}
          </main>
        )}

        <CompanyFooter
          tenant={tenant}
          settings={settings}
          items={data?.chrome?.navigation.footer ?? []}
          labels={{
            explore: t("company_profile.footer_explore"),
            social: t("company_profile.footer_social"),
            contact: t("company_profile.footer_contact"),
            hours: t("company_profile.footer_hours"),
            closed: t("company_profile.closed"),
          }}
        />
      </div>
    </CompanyLocaleProvider>
  )
}

/**
 * Harga dan durasi dari katalog layanan. Treatment yang belum ditautkan ke
 * layanan tidak menampilkan apa pun — angka kosong atau "hubungi kami" di
 * tempat harga justru membuat orang menutup halaman.
 */
function PriceBlock({
  treatment,
  labels,
}: {
  treatment: { price?: string | null; duration_minutes?: number | null; promo?: { price: string } | null }
  labels: { from: string; promo: string; minutes: string }
}) {
  if (!treatment.price && !treatment.duration_minutes) return null

  const price = treatment.price ? Number(treatment.price) : null
  const promo = treatment.promo ? Number(treatment.promo.price) : null

  return (
    <div className="mt-6 flex flex-wrap items-end gap-x-8 gap-y-3 rounded-xl border border-border/60 bg-muted/30 px-5 py-4">
      {price !== null ? (
        <div>
          <p className="text-2xs tracking-[0.14em] text-muted-foreground uppercase">
            {promo !== null ? labels.promo : labels.from}
          </p>
          <p className="mt-1 flex items-baseline gap-2">
            <span className="text-xl font-semibold tracking-tight tabular-nums">
              {formatCurrency(promo ?? price)}
            </span>
            {/* Harga normal tetap ditulis saat promo berlaku: potongan yang
                tidak bisa dibandingkan tidak terasa sebagai potongan. */}
            {promo !== null ? (
              <span className="text-sm text-muted-foreground line-through tabular-nums">
                {formatCurrency(price)}
              </span>
            ) : null}
          </p>
        </div>
      ) : null}

      {treatment.duration_minutes ? (
        <p className="flex items-center gap-1.5 pb-1 text-sm text-muted-foreground">
          <HugeiconsIcon
            icon={Clock01Icon}
            strokeWidth={1.8}
            className="size-4"
          />
          <span className="tabular-nums">
            {treatment.duration_minutes} {labels.minutes}
          </span>
        </p>
      ) : null}
    </div>
  )
}

/**
 * Dua jalan keluar: booking sendiri, atau bertanya dulu lewat WhatsApp.
 * Pesannya sudah membawa nama treatment — pasien tidak perlu mengetik ulang,
 * dan staf tidak perlu menebak yang ditanyakan.
 */
function BookingActions({
  tenant,
  settings,
  title,
  labels,
}: {
  tenant: string
  settings: CompanySettings | null
  title?: string | null
  labels: { book: string; ask: string; template: string }
}) {
  const whatsapp = settings?.chat_channels?.find(
    (channel) => channel.type === "whatsapp",
  )

  const askUrl = whatsapp
    ? `${whatsapp.url}${whatsapp.url.includes("?") ? "&" : "?"}text=${encodeURIComponent(
        labels.template.replace(":treatment", title ?? ""),
      )}`
    : null

  return (
    <div className="mt-5 flex flex-wrap gap-2.5">
      <Button
        asChild
        size="lg"
        className="transition-transform duration-150 ease-out hover:-translate-y-0.5"
      >
        <Link to="/$tenant" params={{ tenant }} search={{ lang: undefined }} hash="booking">
          {labels.book}
        </Link>
      </Button>

      {askUrl ? (
        <Button asChild size="lg" variant="outline">
          <a href={askUrl} target="_blank" rel="noreferrer noopener">
            <HugeiconsIcon icon={WhatsappIcon} strokeWidth={1.8} className="size-4" />
            {labels.ask}
          </a>
        </Button>
      ) : null}
    </div>
  )
}

import { createFileRoute, Link } from "@tanstack/react-router"

import { CtaLink } from "#/components/company-profile/cta-link.tsx"
import { useContentLocale } from "#/components/company-profile/locale-context.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"

export const Route = createFileRoute("/_marketing/online-booking/")({
  component: OnlineBookingPage,
})

const WHATSAPP = "https://wa.me/6281234567890"

function OnlineBookingPage() {
  const locale = useContentLocale()
  const isEnglish = locale === "en"

  const steps = isEnglish
    ? [
        {
          title: "Tell us what you need",
          body: "Send us the treatment you have in mind, or just describe your skin concern — we will suggest one.",
        },
        {
          title: "Pick a time",
          body: "Choose the outlet and time slot that works for you. We confirm within minutes during opening hours.",
        },
        {
          title: "Free consultation first",
          body: "Our doctor maps your skin condition before any procedure starts. No treatment is done without your go-ahead.",
        },
      ]
    : [
        {
          title: "Sampaikan kebutuhan Anda",
          body: "Kirim treatment yang Anda incar, atau ceritakan saja keluhan kulitnya — kami yang menyarankan.",
        },
        {
          title: "Pilih jadwal",
          body: "Tentukan outlet dan jam yang pas. Kami konfirmasi dalam hitungan menit selama jam buka.",
        },
        {
          title: "Konsultasi gratis dulu",
          body: "Dokter kami memetakan kondisi kulit sebelum tindakan dimulai. Tidak ada treatment tanpa persetujuan Anda.",
        },
      ]

  return (
    <MarketingPage
      crumbs={[{ label: "Online Booking" }]}
      title={isEnglish ? "Book an appointment" : "Booking jadwal perawatan"}
      description={
        isEnglish
          ? "Booking runs over WhatsApp so you talk to a real person, not a form."
          : "Booking dilakukan lewat WhatsApp supaya Anda bicara dengan orang, bukan mengisi formulir."
      }
    >
      <div className="space-y-6">
        <ol className="grid gap-px overflow-hidden rounded-lg border border-border/50 bg-border/50 sm:grid-cols-3">
          {steps.map((step, index) => (
            <li key={step.title} className="bg-background p-5">
              <span className="mb-3 inline-flex size-7 items-center justify-center rounded-full bg-muted text-xs font-medium tabular-nums">
                {index + 1}
              </span>
              <h2 className="text-sm font-semibold tracking-tight">
                {step.title}
              </h2>
              <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                {step.body}
              </p>
            </li>
          ))}
        </ol>

        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border/50 bg-muted/60 p-6">
          <div className="mr-auto max-w-lg">
            <h2 className="text-base font-semibold tracking-tight">
              {isEnglish ? "Ready when you are" : "Kami siap kapan pun Anda siap"}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {isEnglish
                ? "Opening hours: every day, 9 AM – 8 PM."
                : "Jam buka: setiap hari, 09.00–20.00."}
            </p>
          </div>
          <CtaLink
            tenant=""
            label={{ id: "Booking via WhatsApp", en: "Book via WhatsApp" }}
            type="whatsapp"
            url={WHATSAPP}
          />
          <Button asChild variant="outline">
            <Link to="/locations" search={{ lang: locale }}>
              {isEnglish ? "See outlets" : "Lihat outlet"}
            </Link>
          </Button>
        </div>
      </div>
    </MarketingPage>
  )
}

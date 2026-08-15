import { createFileRoute } from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"
import { Call02Icon, Clock01Icon, Location01Icon } from "@hugeicons/core-free-icons"

import { useContentLocale, useContentText } from "#/components/company-profile/locale-context.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { LOCATIONS } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/locations/")({
  component: LocationsPage,
})

function LocationsPage() {
  const text = useContentText()
  const isEnglish = useContentLocale() === "en"

  return (
    <MarketingPage
      crumbs={[{ label: isEnglish ? "Locations" : "Lokasi" }]}
      title={isEnglish ? "Our outlets" : "Outlet kami"}
      description={
        isEnglish
          ? "Same protocol, same standard, in every city. Your treatment history follows you."
          : "Protokol dan standar yang sama di setiap kota. Riwayat perawatan Anda ikut berpindah."
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        {LOCATIONS.map((location) => (
          <article
            key={location.id}
            className="space-y-3 rounded-lg border border-border/50 bg-background p-5 transition-colors hover:border-border"
          >
            <div>
              <h2 className="text-sm font-semibold tracking-tight">
                {location.name}
              </h2>
              <p className="text-xs text-muted-foreground">
                {text(location.city)}
              </p>
            </div>

            <dl className="space-y-1.5 text-sm text-muted-foreground">
              <Row icon={Location01Icon} value={text(location.address) ?? "-"} />
              <Row icon={Clock01Icon} value={text(location.hours) ?? "-"} />
              <Row icon={Call02Icon}>
                <a
                  href={`tel:${location.phone}`}
                  className="tabular-nums no-underline transition-colors hover:text-foreground"
                >
                  {location.phone}
                </a>
              </Row>
            </dl>
          </article>
        ))}
      </div>
    </MarketingPage>
  )
}

function Row({
  icon,
  value,
  children,
}: {
  icon: React.ComponentProps<typeof HugeiconsIcon>["icon"]
  value?: string
  children?: React.ReactNode
}) {
  return (
    <div className="flex items-start gap-2">
      <HugeiconsIcon
        icon={icon}
        strokeWidth={2}
        className="mt-0.5 size-4 shrink-0"
      />
      <span>{children ?? value}</span>
    </div>
  )
}

import { createFileRoute, Link } from "@tanstack/react-router"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  Building03Icon,
  Calendar01Icon,
  Car01Icon,
  Navigation03Icon,
} from "@hugeicons/core-free-icons"

import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { contentText } from "#/lib/company-locale.ts"
import { OUTLET } from "#/lib/clinic-outlet.ts"
import { OutletContact } from "./components/outlet-contact.tsx"
import { OutletHours } from "./components/outlet-hours.tsx"
import { OutletMap } from "./components/outlet-map.tsx"

export const Route = createFileRoute("/_marketing/locations/")({
  component: LocationsPage,
})

/**
 * Satu outlet, satu halaman. Selama cabangnya masih satu, daftar kartu justru
 * menyesatkan — halaman ini langsung menampilkan alamat, peta, dan jamnya.
 * Kembalikan ke bentuk daftar saat outlet kedua benar-benar dibuka.
 */
function LocationsPage() {
  const text = contentText
  const directions = text(OUTLET.gettingHere) ?? []

  return (
    <MarketingPage
      crumbs={[{ label: "Lokasi" }]}
      title="Tempat kami berada"
      description="Satu klinik, dirawat sepenuh hati. Datang untuk konsultasi, atau kabari dulu lewat pesan — kami sisakan jadwalnya untuk Anda."
    >
      {/* Blok utama: peta di kiri, alamat dan kontak di kanan. */}
      <section className="overflow-hidden rounded-xl border border-border bg-background">
        <div className="grid gap-0 lg:grid-cols-[1.15fr_1fr]">
          <div className="order-2 flex flex-col gap-5 p-6 lg:order-1 lg:p-7">
            <div>
              <p className="island-kicker mb-2">Klinik kami</p>
              <h2 className="display-title text-2xl font-bold tracking-tight text-balance">
                {OUTLET.name}
              </h2>
              <p className="mt-1 text-sm text-muted-foreground">
                {text(OUTLET.city)}
              </p>
            </div>

            <OutletContact outlet={OUTLET} />

            <div className="mt-auto flex flex-wrap gap-2 pt-1">
              <Button
                asChild
                className="transition-transform duration-150 ease-out hover:-translate-y-px"
              >
                <Link to="/online-booking">
                  <HugeiconsIcon
                    icon={Calendar01Icon}
                    strokeWidth={2}
                    className="size-4"
                  />
                  Buat janji
                </Link>
              </Button>
              <Button asChild variant="outline">
                <a
                  href={OUTLET.mapsUrl}
                  target="_blank"
                  rel="noreferrer noopener"
                >
                  <HugeiconsIcon
                    icon={Navigation03Icon}
                    strokeWidth={2}
                    className="size-4"
                  />
                  Petunjuk arah
                </a>
              </Button>
            </div>
          </div>

          <div className="order-1 lg:order-2">
            <OutletMap
              query={OUTLET.mapQuery}
              mapsUrl={OUTLET.mapsUrl}
              name={OUTLET.name}
            />
          </div>
        </div>
      </section>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <OutletHours hours={OUTLET.openingHours} />

        <section className="rounded-lg border border-border bg-background p-5">
          <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold tracking-tight">
            <HugeiconsIcon
              icon={Car01Icon}
              strokeWidth={2}
              className="size-4 text-muted-foreground"
            />
            Cara ke sini
          </h2>

          {directions.length > 0 ? (
            <ul className="space-y-2.5">
              {directions.map((hint) => (
                <li key={hint} className="flex items-start gap-2.5 text-sm">
                  <HugeiconsIcon
                    icon={Building03Icon}
                    strokeWidth={2}
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                  />
                  <span className="leading-relaxed text-muted-foreground">
                    {hint}
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm leading-relaxed text-muted-foreground">
              Buka petanya untuk rute langsung dari posisi Anda. Telepon saja
              kalau pintu masuknya sulit terlihat, nanti kami pandu.
            </p>
          )}
        </section>
      </div>
    </MarketingPage>
  )
}

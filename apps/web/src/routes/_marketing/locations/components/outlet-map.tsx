import { HugeiconsIcon } from "@hugeicons/react"
import { MapsLocation01Icon, Navigation03Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { useContentLocale } from "#/components/company-profile/locale-context.tsx"

interface OutletMapProps {
  /** Kueri alamat untuk peta sematan; kosong berarti belum pasti. */
  query: string
  mapsUrl: string
  name: string
}

/**
 * Peta outlet. Google melayani sematan lewat `?q=…&output=embed` tanpa kunci
 * API, jadi alamat lengkap sudah cukup.
 *
 * Saat kueri belum pasti, yang tampil kartu tautan — bukan peta yang menunjuk
 * tempat lain. Peta yang salah alamat lebih berbahaya daripada tidak ada peta.
 */
export function OutletMap({ query, mapsUrl, name }: OutletMapProps) {
  const isEnglish = useContentLocale() === "en"

  if (!query) {
    return (
      <div className="flex h-full min-h-[280px] flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border/60 bg-muted/30 p-8 text-center">
        <span className="flex size-11 items-center justify-center rounded-full bg-background text-muted-foreground ring-1 ring-border/60">
          <HugeiconsIcon
            icon={MapsLocation01Icon}
            strokeWidth={2}
            className="size-5"
          />
        </span>
        <div>
          <p className="text-sm font-medium">
            {isEnglish ? "Open in Google Maps" : "Buka di Google Maps"}
          </p>
          <p className="mx-auto mt-1 max-w-xs text-xs leading-relaxed text-muted-foreground">
            {isEnglish
              ? "The pinned location is live on Maps, with directions from wherever you are."
              : "Titik lokasinya sudah ada di Maps, lengkap dengan rute dari posisi Anda."}
          </p>
        </div>
        <Button asChild size="sm" variant="outline">
          <a href={mapsUrl} target="_blank" rel="noreferrer noopener">
            <HugeiconsIcon
              icon={Navigation03Icon}
              strokeWidth={2}
              className="size-4"
            />
            {isEnglish ? "Get directions" : "Petunjuk arah"}
          </a>
        </Button>
      </div>
    )
  }

  return (
    <div className="relative h-full min-h-[280px] overflow-hidden rounded-lg border border-border/50">
      <iframe
        title={
          isEnglish ? `Map to ${name}` : `Peta menuju ${name}`
        }
        src={`https://maps.google.com/maps?q=${encodeURIComponent(query)}&output=embed`}
        loading="lazy"
        referrerPolicy="no-referrer-when-downgrade"
        className="size-full min-h-[280px] border-0 grayscale-[0.15] transition-[filter] duration-300 hover:grayscale-0"
      />

      {/* Tombol mengambang supaya rute tetap sekali klik tanpa menutupi peta. */}
      <Button
        asChild
        size="sm"
        className="absolute right-3 bottom-3 shadow-sm transition-transform duration-150 ease-out hover:-translate-y-px"
      >
        <a href={mapsUrl} target="_blank" rel="noreferrer noopener">
          <HugeiconsIcon
            icon={Navigation03Icon}
            strokeWidth={2}
            className="size-4"
          />
          {isEnglish ? "Get directions" : "Petunjuk arah"}
        </a>
      </Button>
    </div>
  )
}

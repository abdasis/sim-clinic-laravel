import { useCallback, useEffect, useState } from "react"

import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
  type CarouselApi,
} from "#/components/ui/carousel.tsx"
import { cn } from "#/lib/utils.ts"
import type { CompanySlide } from "#/hooks/use-company-profile.ts"
import { CtaLink } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"

const ROTATE_MS = 6000

interface HeroCarouselProps {
  tenant: string
  slides: CompanySlide[]
}

/**
 * Hero berputar sendiri, tapi berhenti begitu pengunjung menyentuhnya —
 * slide yang lompat saat sedang dibaca lebih mengganggu daripada membantu.
 */
export function HeroCarousel({ tenant, slides }: HeroCarouselProps) {
  const text = useContentText()
  const [api, setApi] = useState<CarouselApi>()
  const [current, setCurrent] = useState(0)
  const [paused, setPaused] = useState(false)

  useEffect(() => {
    if (!api) return

    const sync = () => setCurrent(api.selectedScrollSnap())

    sync()
    api.on("select", sync)

    return () => {
      api.off("select", sync)
    }
  }, [api])

  useEffect(() => {
    if (!api || paused || slides.length < 2) return

    const timer = setInterval(() => api.scrollNext(), ROTATE_MS)

    return () => clearInterval(timer)
  }, [api, paused, slides.length])

  const goTo = useCallback((index: number) => api?.scrollTo(index), [api])

  if (slides.length === 0) return null

  return (
    <section
      className="relative"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocusCapture={() => setPaused(true)}
      onBlurCapture={() => setPaused(false)}
    >
      <Carousel setApi={setApi} opts={{ loop: true }}>
        <CarouselContent>
          {slides.map((slide) => {
            const title = text(slide.title)

            return (
              <CarouselItem key={slide.id}>
                {/* Tinggi mengikuti viewport dengan batas atas: hero setinggi
                    380px di layar 1080 terbaca sebagai pita, bukan pembuka. */}
                <div className="relative h-[78svh] max-h-[680px] min-h-[440px] w-full overflow-hidden">
                  {slide.image_url ? (
                    <img
                      src={slide.image_url}
                      alt={title ?? ""}
                      className="absolute inset-0 size-full object-cover"
                    />
                  ) : (
                    <div className="absolute inset-0 bg-muted" />
                  )}

                  {/* Dua lapis peredup, bukan satu: lapis mendatar menjaga
                      sisi teks tetap pekat, lapis menurun menahan bagian
                      bawah supaya kontrolnya tetap terbaca di atas foto
                      terang mana pun. */}
                  <div className="absolute inset-0 bg-gradient-to-r from-neutral-950/85 via-neutral-950/55 to-neutral-950/10" />
                  <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-neutral-950/60 to-transparent" />

                  <div className="relative mx-auto flex h-full w-full max-w-6xl items-center px-4 sm:px-6">
                    <div className="max-w-xl pb-14">
                      {title ? (
                        <h1 className="text-3xl leading-[1.08] font-semibold tracking-tight text-balance text-white sm:text-5xl lg:text-[3.25rem]">
                          {title}
                        </h1>
                      ) : null}
                      {text(slide.subtitle) ? (
                        <p className="mt-4 max-w-md text-sm leading-relaxed text-pretty text-white/75 sm:text-base">
                          {text(slide.subtitle)}
                        </p>
                      ) : null}
                      <CtaLink
                        tenant={tenant}
                        label={slide.cta_label}
                        type={slide.cta_type}
                        url={slide.cta_url}
                        size="lg"
                        className="mt-7 shadow-sm transition-transform duration-200 hover:-translate-y-0.5 active:scale-[0.96]"
                      />
                    </div>
                  </div>
                </div>
              </CarouselItem>
            )
          })}
        </CarouselContent>

        {slides.length > 1 ? (
          <>
            <CarouselPrevious className="left-4 border-white/25 bg-white/10 text-white backdrop-blur-sm transition-transform duration-150 hover:bg-white/20 hover:text-white active:scale-[0.96]" />
            <CarouselNext className="right-4 border-white/25 bg-white/10 text-white backdrop-blur-sm transition-transform duration-150 hover:bg-white/20 hover:text-white active:scale-[0.96]" />
          </>
        ) : null}
      </Carousel>

      {slides.length > 1 ? (
        // Sejajar kolom teks, bukan tengah layar: penanda slide bagian dari
        // blok konten, sehingga matanya tidak perlu pindah untuk melihatnya.
        <div className="pointer-events-none absolute inset-x-0 bottom-7">
          <div className="mx-auto flex w-full max-w-6xl items-center gap-2 px-4 sm:px-6">
            {slides.map((slide, index) => (
              <button
                key={slide.id}
                type="button"
                aria-label={`Slide ${index + 1}`}
                aria-current={index === current}
                onClick={() => goTo(index)}
                // Ponytail: dot visual kecil (h-1), tapi hit area diperluas
                // pseudo-element ke 40px supaya targetnya nyaman disentuh.
                className={cn(
                  "pointer-events-auto relative flex h-10 w-10 items-center justify-center rounded-full transition-colors duration-300 focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:outline-none",
                  "after:absolute after:inset-0 after:rounded-full",
                )}
              >
                <span
                  className={cn(
                    "block h-1 rounded-full transition-[width,background-color] duration-300",
                    index === current
                      ? "w-8 bg-white"
                      : "w-3 bg-white/40 hover:bg-white/70",
                  )}
                />
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </section>
  )
}

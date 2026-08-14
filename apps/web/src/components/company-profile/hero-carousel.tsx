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
                <div className="relative h-[380px] w-full overflow-hidden sm:h-[460px]">
                  {slide.image_url ? (
                    <img
                      src={slide.image_url}
                      alt={title ?? ""}
                      className="absolute inset-0 size-full object-cover"
                    />
                  ) : (
                    <div className="absolute inset-0 bg-muted" />
                  )}
                  <div className="absolute inset-0 bg-gradient-to-r from-background/90 via-background/60 to-transparent" />
                  <div className="relative mx-auto flex h-full w-full max-w-6xl items-center px-4">
                    <div className="max-w-lg space-y-3">
                      {title ? (
                        <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                          {title}
                        </h1>
                      ) : null}
                      {text(slide.subtitle) ? (
                        <p className="text-sm leading-relaxed text-muted-foreground text-pretty sm:text-base">
                          {text(slide.subtitle)}
                        </p>
                      ) : null}
                      <CtaLink
                        tenant={tenant}
                        label={slide.cta_label}
                        type={slide.cta_type}
                        url={slide.cta_url}
                        className="mt-1"
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
            <CarouselPrevious className="left-4" />
            <CarouselNext className="right-4" />
          </>
        ) : null}
      </Carousel>

      {slides.length > 1 ? (
        <div className="absolute bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-1.5">
          {slides.map((slide, index) => (
            <button
              key={slide.id}
              type="button"
              aria-label={`Slide ${index + 1}`}
              aria-current={index === current}
              onClick={() => goTo(index)}
              className={cn(
                "h-1.5 rounded-full transition-all duration-300 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                index === current
                  ? "w-6 bg-foreground"
                  : "w-1.5 bg-foreground/30 hover:bg-foreground/50",
              )}
            />
          ))}
        </div>
      ) : null}
    </section>
  )
}

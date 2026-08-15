import { HugeiconsIcon, type IconSvgElement } from "@hugeicons/react"
import { motion, useReducedMotion } from "motion/react"

import { Button } from "#/components/ui/button.tsx"
import { cn } from "#/lib/utils.ts"
import { Mascot, type MascotVariant } from "./mascot.tsx"

/** Nuansa banner; kelasnya hanya menggeser --cta-accent (lihat styles.css). */
export type CtaTone = "lagoon" | "palm" | "sand" | "ink" | "sunset"

const TONE_CLASS: Record<CtaTone, string> = {
  lagoon: "cta-lagoon",
  palm: "cta-palm",
  sand: "cta-sand",
  ink: "cta-ink",
  sunset: "cta-sunset",
}

interface IndexCtaProps {
  icon: IconSvgElement
  title: string
  description: string
  tone?: CtaTone
  mascot?: MascotVariant
  /** Aksi berupa tombol biasa, mis. membuka dialog yang sudah ada. */
  actionLabel?: string
  onAction?: () => void
  /**
   * Aksi berupa tautan. `Link` menuntut `to` berupa literal supaya tipenya
   * terperiksa, jadi tombolnya dirakit di halaman dan dititipkan ke sini.
   */
  action?: React.ReactNode
  className?: string
}

/**
 * Banner pengantar di kepala halaman index: menjelaskan gunanya halaman ini
 * dan menawarkan satu langkah berikutnya. Tanpa aksi, banner tidak dirender.
 */
export function IndexCta({
  icon,
  title,
  description,
  tone = "lagoon",
  mascot,
  actionLabel,
  onAction,
  action,
  className,
}: IndexCtaProps) {
  const reduced = useReducedMotion()

  if (!action && !(actionLabel && onAction)) return null

  const stagger = (order: number) => ({
    initial: reduced ? false : { opacity: 0, y: 8 },
    animate: { opacity: 1, y: 0 },
    transition: {
      duration: 0.4,
      delay: reduced ? 0 : 0.06 * order,
      ease: [0.16, 1, 0.3, 1] as const,
    },
  })

  return (
    <motion.section
      initial={reduced ? false : { opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] }}
      className={cn(
        "cta-banner mb-6 rounded-lg px-5 py-5 shadow-sm sm:px-6",
        TONE_CLASS[tone],
        className,
      )}
    >
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
        <div className="min-w-0 flex-1">
          <motion.div
            {...stagger(0)}
            className="mb-2 inline-flex size-8 items-center justify-center rounded-md text-[var(--cta-accent)] ring-1 ring-[color-mix(in_oklab,var(--cta-accent)_28%,transparent)]"
          >
            <HugeiconsIcon icon={icon} strokeWidth={2} className="size-4" />
          </motion.div>

          <motion.h2
            {...stagger(1)}
            className="text-base font-semibold tracking-tight text-balance"
          >
            {title}
          </motion.h2>

          <motion.p
            {...stagger(2)}
            className="mt-1 max-w-xl text-sm leading-relaxed text-muted-foreground"
          >
            {description}
          </motion.p>

          <motion.div {...stagger(3)} className="mt-4">
            {action ?? (
              <Button
                type="button"
                onClick={onAction}
                className="transition-transform duration-150 ease-out hover:-translate-y-px"
              >
                {actionLabel}
              </Button>
            )}
          </motion.div>
        </div>

        {mascot ? (
          <motion.div
            initial={reduced ? false : { opacity: 0, scale: 0.94 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, delay: reduced ? 0 : 0.12, ease: [0.16, 1, 0.3, 1] }}
            aria-hidden="true"
            className="hidden shrink-0 text-[var(--cta-accent)] sm:block"
          >
            <Mascot variant={mascot} className="size-28" />
          </motion.div>
        ) : null}
      </div>
    </motion.section>
  )
}

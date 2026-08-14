import { cn } from "#/lib/utils.ts"

interface SectionShellProps {
  id?: string
  eyebrow?: string
  title?: string | null
  description?: string | null
  className?: string
  children: React.ReactNode
}

/**
 * Bingkai seragam tiap section landing: lebar konten, ritme jarak, dan
 * hierarki judul yang sama supaya halaman tidak terasa tambal sulam.
 */
export function SectionShell({
  id,
  eyebrow,
  title,
  description,
  className,
  children,
}: SectionShellProps) {
  return (
    <section
      id={id}
      className={cn("scroll-mt-20 border-t border-border/50 py-14", className)}
    >
      <div className="mx-auto w-full max-w-6xl px-4">
        {title || eyebrow ? (
          <header className="mb-8 max-w-2xl">
            {eyebrow ? (
              <p className="mb-1.5 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                {eyebrow}
              </p>
            ) : null}
            {title ? (
              <h2 className="text-2xl font-semibold tracking-tight text-balance">
                {title}
              </h2>
            ) : null}
            {description ? (
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground text-pretty">
                {description}
              </p>
            ) : null}
          </header>
        ) : null}
        {children}
      </div>
    </section>
  )
}

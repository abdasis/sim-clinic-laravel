import { cn } from "#/lib/utils.ts"

export type SectionTone = "default" | "muted"

interface SectionShellProps {
  id?: string
  eyebrow?: string
  title?: string | null
  description?: string | null
  /**
   * Latar section. Halaman yang seluruh sectionnya berlatar sama terbaca
   * sebagai satu tumpukan pita seragam — mata tidak punya penanda pindah
   * bagian. Ditukar selang-seling supaya batas antar section terasa tanpa
   * perlu garis.
   */
  tone?: SectionTone
  /** Aksi kecil di sisi kanan judul, mis. tautan "lihat semua". */
  action?: React.ReactNode
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
  tone = "default",
  action,
  className,
  children,
}: SectionShellProps) {
  return (
    <section
      id={id}
      className={cn(
        // Garis batas selalu satu sisi supaya pertemuan dua section tidak
        // menumpuk jadi garis ganda; nada latarlah yang membedakannya.
        "scroll-mt-16 border-t border-border/40 py-16 sm:py-20",
        tone === "muted" ? "bg-muted/40" : "",
        className,
      )}
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
        {title || eyebrow ? (
          <header className="mb-9 flex flex-wrap items-end justify-between gap-x-8 gap-y-3">
            <div className="max-w-2xl">
              {eyebrow ? (
                // Penanda kecil sebelum judul: memberi judul sesuatu untuk
                // berdiri di atasnya, sehingga tidak melayang sendirian.
                <p className="mb-2.5 flex items-center gap-2 text-2xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                  <span
                    aria-hidden="true"
                    className="h-px w-6 bg-foreground/25"
                  />
                  {eyebrow}
                </p>
              ) : null}
              {title ? (
                <h2 className="text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                  {title}
                </h2>
              ) : null}
              {description ? (
                <p className="mt-2.5 text-sm leading-relaxed text-muted-foreground text-pretty">
                  {description}
                </p>
              ) : null}
            </div>
            {action ? <div className="shrink-0">{action}</div> : null}
          </header>
        ) : null}
        {children}
      </div>
    </section>
  )
}

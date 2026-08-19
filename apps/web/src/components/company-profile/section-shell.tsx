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
  /** Aksi kecil di bawah judul, mis. tautan "lihat semua". */
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
        {title ? (
          // Judul di tengah, huruf besar berjarak lebar, lalu garis pendek —
          // pola kepala section yang sudah lazim di situs klinik Indonesia.
          // Yang membedakannya di sini: jaraknya dijaga tetap sama di semua
          // section supaya halamannya terbaca sebagai satu dokumen.
          <header className="mb-10 text-center">
            {eyebrow ? (
              <p className="mb-3 text-2xs font-semibold tracking-[0.22em] text-primary/70 uppercase">
                {eyebrow}
              </p>
            ) : null}
            <h2 className="text-lg font-medium tracking-[0.16em] text-balance uppercase sm:text-2xl">
              {title}
            </h2>
            <span
              aria-hidden="true"
              className="mx-auto mt-4 block h-px w-16 bg-foreground/25"
            />
            {description ? (
              <p className="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-pretty text-muted-foreground sm:text-base">
                {description}
              </p>
            ) : null}
            {action ? <div className="mt-5">{action}</div> : null}
          </header>
        ) : null}
        {children}
      </div>
    </section>
  )
}

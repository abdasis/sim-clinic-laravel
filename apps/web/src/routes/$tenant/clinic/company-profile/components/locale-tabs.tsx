import { Check } from "lucide-react"

import { CONTENT_LOCALES, type ContentLocale } from "#/lib/company-locale.ts"
import { cn } from "#/lib/utils.ts"

interface LocaleTabsProps {
  locale: ContentLocale
  filled: ContentLocale[]
  onChange: (locale: ContentLocale) => void
}

/**
 * Pemilih bahasa untuk satu field. Bahasa yang sudah terisi ditandai centang
 * supaya admin tahu versi mana yang masih kosong tanpa membukanya satu-satu.
 */
export function LocaleTabs({ locale, filled, onChange }: LocaleTabsProps) {
  return (
    <div className="flex items-center gap-0.5 rounded-md border border-border/50 p-0.5">
      {CONTENT_LOCALES.map((code) => (
        <button
          key={code}
          type="button"
          aria-pressed={code === locale}
          onClick={() => onChange(code)}
          className={cn(
            "flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-2xs font-medium tracking-wide uppercase transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
            code === locale
              ? "bg-muted text-foreground"
              : "text-muted-foreground hover:text-foreground",
          )}
        >
          {code}
          {filled.includes(code) ? (
            <Check className="size-2.5" aria-hidden />
          ) : null}
        </button>
      ))}
    </div>
  )
}

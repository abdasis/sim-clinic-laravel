import { useState } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  Call02Icon,
  CheckmarkCircle02Icon,
  Copy01Icon,
  Location01Icon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  useContentLocale,
  useContentText,
} from "#/components/company-profile/locale-context.tsx"
import { flatAddress } from "#/lib/clinic-outlet.ts"
import type { ClinicOutlet } from "#/lib/clinic-outlet.ts"

/** Alamat, kontak, dan aksi — semuanya dalam satu kolom di sebelah peta. */
export function OutletContact({ outlet }: { outlet: ClinicOutlet }) {
  const text = useContentText()
  const isEnglish = useContentLocale() === "en"

  const lines = text(outlet.addressLines) ?? []
  const address = flatAddress(lines, outlet.postalCode)

  return (
    <div className="flex flex-col gap-5">
      <div>
        <p className="mb-2 flex items-center gap-2 text-[11px] font-medium tracking-widest text-muted-foreground uppercase">
          <HugeiconsIcon
            icon={Location01Icon}
            strokeWidth={2}
            className="size-3.5"
          />
          {isEnglish ? "Address" : "Alamat"}
        </p>
        <address className="text-sm leading-relaxed not-italic text-pretty">
          {lines.map((line) => (
            <span key={line} className="block">
              {line}
            </span>
          ))}
          {outlet.postalCode ? (
            <span className="block tabular-nums">{outlet.postalCode}</span>
          ) : null}
        </address>

        {address ? <CopyAddress value={address} /> : null}
      </div>

      <div className="flex flex-wrap gap-2">
        {outlet.phone ? (
          <Button asChild variant="outline" size="sm">
            <a href={`tel:${outlet.phone.replace(/\s/g, "")}`}>
              <HugeiconsIcon icon={Call02Icon} strokeWidth={2} className="size-4" />
              <span className="tabular-nums">{outlet.phone}</span>
            </a>
          </Button>
        ) : null}

        {outlet.whatsapp ? (
          <Button asChild variant="outline" size="sm">
            <a
              href={`https://wa.me/${outlet.whatsapp}`}
              target="_blank"
              rel="noreferrer noopener"
            >
              <HugeiconsIcon icon={WhatsappIcon} strokeWidth={2} className="size-4" />
              WhatsApp
            </a>
          </Button>
        ) : null}
      </div>
    </div>
  )
}

/**
 * Menyalin alamat jauh lebih sering dibutuhkan daripada kelihatannya — untuk
 * ditempel ke ojek daring. Umpan baliknya di tombol itu sendiri, bukan toast,
 * supaya mata tidak perlu pindah.
 */
function CopyAddress({ value }: { value: string }) {
  const isEnglish = useContentLocale() === "en"
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      // Klipbor bisa ditolak browser; alamatnya tetap terbaca di layar.
    }
  }

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      onClick={copy}
      className="mt-2 h-7 gap-1.5 px-2 text-xs text-muted-foreground hover:text-foreground"
    >
      <HugeiconsIcon
        icon={copied ? CheckmarkCircle02Icon : Copy01Icon}
        strokeWidth={2}
        className="size-3.5"
      />
      {copied
        ? isEnglish
          ? "Copied"
          : "Tersalin"
        : isEnglish
          ? "Copy address"
          : "Salin alamat"}
    </Button>
  )
}

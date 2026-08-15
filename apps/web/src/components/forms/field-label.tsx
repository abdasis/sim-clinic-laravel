import { InformationCircleIcon } from "@hugeicons/core-free-icons"
import { HugeiconsIcon } from "@hugeicons/react"

import { FormLabel } from "#/components/ui/form.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"

/**
 * Label field dengan penanda wajib. Tandanya dekoratif — `aria-required` di
 * input yang memberi tahu pembaca layar, jadi bintangnya disembunyikan supaya
 * tidak dibacakan sebagai "bintang" di tengah nama field.
 *
 * `tooltip` untuk field yang maksudnya tidak langsung terbaca dari namanya;
 * teksnya ikut dipasang sebagai `title` supaya tetap terbaca tanpa hover.
 */
export function FieldLabel({
  label,
  required,
  tooltip,
}: {
  label: string
  required?: boolean
  tooltip?: string
}) {
  return (
    <FormLabel>
      {label}
      {required ? (
        <span aria-hidden="true" className="text-destructive">
          *
        </span>
      ) : null}
      {tooltip ? (
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger
              type="button"
              tabIndex={-1}
              aria-label={tooltip}
              title={tooltip}
              className="text-muted-foreground/70 hover:text-foreground focus-visible:ring-ring/50 rounded-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
            >
              <HugeiconsIcon
                icon={InformationCircleIcon}
                strokeWidth={2}
                className="size-3.5"
              />
            </TooltipTrigger>
            <TooltipContent className="max-w-64">{tooltip}</TooltipContent>
          </Tooltip>
        </TooltipProvider>
      ) : null}
    </FormLabel>
  )
}

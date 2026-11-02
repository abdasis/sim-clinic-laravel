import { HugeiconsIcon } from "@hugeicons/react"

import { Spinner } from "#/components/ui/spinner.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import type { AttachmentItem } from "./attachment-config.ts"

interface AttachmentMenuItemProps {
  item: AttachmentItem
  disabled?: boolean
  busy: boolean
  pending: boolean
  onSelect: (item: AttachmentItem) => void
}

/** Satu baris menu. Item yang tidak didukung tetap terlihat, tapi mati. */
export function AttachmentMenuItem({
  item,
  disabled,
  busy,
  pending,
  onSelect,
}: AttachmentMenuItemProps) {
  const { t } = useTrans()
  const label = t(`inbox.attach.items.${item.key}`)

  if (item.action.type === "disabled") {
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          <span
            role="menuitem"
            tabIndex={0}
            aria-disabled="true"
            data-testid={`attachment-${item.key}`}
            className="flex cursor-not-allowed items-center gap-2.5 rounded-md px-2 py-1.5 text-sm text-muted-foreground opacity-60 outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/40"
          >
            <HugeiconsIcon
              icon={item.icon}
              strokeWidth={1.8}
              className={cn("size-4 shrink-0", item.color)}
            />
            {label}
          </span>
        </TooltipTrigger>
        <TooltipContent>
          {t(`inbox.attach.disabled.${item.action.reason}`)}
        </TooltipContent>
      </Tooltip>
    )
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <button
          type="button"
          role="menuitem"
          data-testid={`attachment-${item.key}`}
          disabled={disabled || busy}
          className="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm outline-none transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-50"
          onClick={() => onSelect(item)}
        >
          {pending ? (
            <Spinner className="size-4 shrink-0" />
          ) : (
            <HugeiconsIcon
              icon={item.icon}
              strokeWidth={1.8}
              className={cn("size-4 shrink-0", item.color)}
            />
          )}
          <span className="truncate">{label}</span>
        </button>
      </TooltipTrigger>
      <TooltipContent className="max-w-56 text-pretty">
        {t(`inbox.attach.hints.${item.key}`)}
      </TooltipContent>
    </Tooltip>
  )
}

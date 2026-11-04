import { HugeiconsIcon } from "@hugeicons/react"
import {
  Copy01Icon,
  Delete02Icon,
  Forward01Icon,
  InformationCircleIcon,
  Note01Icon,
  PinIcon,
  StarIcon,
} from "@hugeicons/core-free-icons"

import {
  ContextMenuItem,
  ContextMenuSeparator,
} from "#/components/ui/context-menu.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

/** Item yang belum bisa dijalankan, beserta alasan yang jujur ke pengguna. */
const UNAVAILABLE_ITEMS = [
  { key: "forward", icon: Forward01Icon },
  { key: "pin", icon: PinIcon },
  { key: "star", icon: StarIcon },
  { key: "note", icon: Note01Icon },
] as const

interface MessageMenuItemsProps {
  onInfo: () => void
  onCopy: () => void
}

/**
 * Isi menu pesan. Yang tidak didukung gateway tetap tampil supaya pengguna
 * tahu fiturnya tidak hilang, dan alasannya muncul saat disorot.
 */
export function MessageMenuItems({ onInfo, onCopy }: MessageMenuItemsProps) {
  const { t } = useTrans()

  return (
    <>
      <div className="p-1">
        <ContextMenuItem data-testid="menu-info" onSelect={onInfo}>
          <HugeiconsIcon icon={InformationCircleIcon} strokeWidth={1.8} />
          {t("inbox.message_menu.info")}
        </ContextMenuItem>
        <ContextMenuItem data-testid="menu-copy" onSelect={onCopy}>
          <HugeiconsIcon icon={Copy01Icon} strokeWidth={1.8} />
          {t("inbox.message_menu.copy")}
        </ContextMenuItem>
      </div>

      <ContextMenuSeparator />

      <div className="p-1">
        {UNAVAILABLE_ITEMS.map((item) => (
          <Tooltip key={item.key}>
            <TooltipTrigger asChild>
              <ContextMenuItem
                aria-disabled="true"
                data-testid={`menu-${item.key}`}
                className="cursor-not-allowed text-muted-foreground"
                onSelect={(event) => event.preventDefault()}
              >
                <HugeiconsIcon icon={item.icon} strokeWidth={1.8} />
                {t(`inbox.message_menu.${item.key}`)}
              </ContextMenuItem>
            </TooltipTrigger>
            <TooltipContent className="max-w-56 text-pretty">
              {t(`inbox.message_menu.disabled.${item.key}`)}
            </TooltipContent>
          </Tooltip>
        ))}
      </div>

      <ContextMenuSeparator />

      <div className="p-1">
        <Tooltip>
          <TooltipTrigger asChild>
            <ContextMenuItem
              aria-disabled="true"
              data-testid="menu-delete"
              className="cursor-not-allowed text-muted-foreground"
              onSelect={(event) => event.preventDefault()}
            >
              <HugeiconsIcon icon={Delete02Icon} strokeWidth={1.8} />
              {t("inbox.message_menu.delete")}
            </ContextMenuItem>
          </TooltipTrigger>
          <TooltipContent className="max-w-56 text-pretty">
            {t("inbox.message_menu.disabled.delete")}
          </TooltipContent>
        </Tooltip>
      </div>
    </>
  )
}

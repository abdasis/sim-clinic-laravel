import { HugeiconsIcon } from "@hugeicons/react"
import {
  ArrowTurnBackwardIcon,
  BubbleChatIcon,
} from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import type { Recipient } from "./broadcast-types.ts"

const STATUS_TONE: Record<string, string> = {
  pending: "border-border/60 text-muted-foreground",
  sent: "border-primary/40 text-primary",
  failed: "border-destructive/40 text-destructive",
  skipped: "border-amber-500/40 text-amber-600 dark:text-amber-400",
}

interface BroadcastRecipientsProps {
  recipients: Recipient[]
  onSendManual: (recipient: Recipient) => void
  onMark: (recipient: Recipient, status: string) => void
}

export function BroadcastRecipients({
  recipients,
  onSendManual,
  onMark,
}: BroadcastRecipientsProps) {
  const { t } = useTrans()

  if (recipients.length === 0) {
    return (
      <p className="rounded-md border border-dashed border-border/60 px-4 py-8 text-center text-sm text-muted-foreground">
        {t("broadcast.no_recipients")}
      </p>
    )
  }

  return (
    <ul className="divide-y divide-border/50 rounded-lg border border-border/50 bg-card">
      {recipients.map((recipient) => (
        <li
          key={recipient.id}
          className="flex flex-wrap items-center gap-3 p-3 transition-colors hover:bg-muted/30"
        >
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <p className="truncate text-sm font-medium">{recipient.name}</p>
              <span className="text-xs tabular-nums text-muted-foreground">
                +{recipient.phone}
              </span>
            </div>
            <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
              {recipient.message}
            </p>
            {recipient.error ? (
              <p className="mt-0.5 text-xs text-destructive">{recipient.error}</p>
            ) : null}
          </div>

          <Badge
            variant="outline"
            className={cn("shrink-0 font-normal", STATUS_TONE[recipient.status] ?? "")}
          >
            {recipient.status_label}
          </Badge>

          {recipient.status === "pending" || recipient.status === "failed" ? (
            <div className="flex shrink-0 gap-1.5">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    size="sm"
                    className="gap-1.5"
                    onClick={() => onSendManual(recipient)}
                  >
                    <HugeiconsIcon
                      icon={BubbleChatIcon}
                      strokeWidth={2}
                      className="size-3.5"
                    />
                    {t("broadcast.send_one")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t("broadcast.send_one")}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    size="sm"
                    variant="ghost"
                    className="text-muted-foreground"
                    onClick={() => onMark(recipient, "skipped")}
                  >
                    {t("broadcast.status.skipped")}
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t("broadcast.mark_skipped")}</TooltipContent>
              </Tooltip>
            </div>
          ) : (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  size="icon"
                  variant="ghost"
                  className="size-9 shrink-0 text-muted-foreground"
                  aria-label={t("broadcast.mark_pending")}
                  onClick={() => onMark(recipient, "pending")}
                >
                  <HugeiconsIcon
                    icon={ArrowTurnBackwardIcon}
                    strokeWidth={2}
                    className="size-3.5"
                  />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("broadcast.mark_pending")}</TooltipContent>
            </Tooltip>
          )}
        </li>
      ))}
    </ul>
  )
}

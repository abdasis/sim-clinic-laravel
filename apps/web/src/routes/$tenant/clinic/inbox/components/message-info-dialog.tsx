import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatDateTime } from "#/lib/format.ts"
import type { Message } from "../types.ts"

interface MessageInfoDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  message: Message
}

/** Rincian teknis satu pesan: cukup untuk melacak satu balasan di log server. */
export function MessageInfoDialog({
  open,
  onOpenChange,
  message,
}: MessageInfoDialogProps) {
  const { t } = useTrans()
  const empty = t("inbox.message_menu.info_empty")
  const status = message.status
    ? (message.status_label ?? t(`inbox.status.${message.status}`))
    : empty

  const rows = [
    { key: "id", label: t("inbox.message_menu.info_id"), value: String(message.id) },
    {
      key: "direction",
      label: t("inbox.message_menu.info_direction"),
      value: message.direction_label ?? t(`inbox.direction.${message.direction}`),
    },
    { key: "status", label: t("inbox.message_menu.info_status"), value: status },
    {
      key: "time",
      label: t("inbox.message_menu.info_time"),
      value: formatDateTime(message.sent_at) || empty,
    },
    {
      key: "provider",
      label: t("inbox.message_menu.info_provider_id"),
      value: message.provider_message_id ?? empty,
    },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("inbox.message_menu.info_title")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("inbox.message_menu.info_hint")}
          </DialogDescription>
        </DialogHeader>

        <dl className="grid gap-1.5 text-sm" data-testid="message-info">
          {rows.map((row) => (
            <div key={row.key} className="flex items-baseline justify-between gap-4">
              <dt className="text-muted-foreground">{row.label}</dt>
              <dd className="min-w-0 truncate text-right font-medium tabular-nums">
                {row.value}
              </dd>
            </div>
          ))}
        </dl>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t("general.close")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

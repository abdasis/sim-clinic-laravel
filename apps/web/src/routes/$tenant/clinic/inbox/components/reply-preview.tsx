import { HugeiconsIcon } from "@hugeicons/react"
import { Cancel01Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import type { Message, MessageReply } from "../types.ts"

/** Isi kutipan: badan pesan, atau nama jenis lampiran bila tanpa teks. */
function quotedText(reply: MessageReply, fallback: string): string {
  const body = reply.body.trim()

  if (body !== "") return body

  return reply.media_type ? `[${fallback}]` : fallback
}

interface ReplyPreviewProps {
  target: Message | null
  contactName: string
  onCancel: () => void
}

/** Bar kutipan di atas kotak balasan: tujuan, potongan pesan, tombol tutup. */
export function ReplyPreview({ target, contactName, onCancel }: ReplyPreviewProps) {
  const { t } = useTrans()
  if (!target) return null

  const sender =
    target.direction === "out" ? t("inbox.message_menu.reply_you") : contactName
  const media = target.media_type
    ? t(`inbox.media.${target.media_type}`)
    : t("inbox.message_menu.reply_fallback")
  const text = quotedText(
    {
      id: target.id,
      body: target.body,
      direction: target.direction,
      media_type: target.media_type,
    },
    media,
  )

  return (
    <div
      data-testid="reply-preview"
      className="flex items-start gap-2 rounded-md border-s-2 border-sky-600 bg-muted/60 py-1.5 pe-1 ps-2.5 dark:border-sky-400"
    >
      <div className="min-w-0 flex-1">
        <p className="text-[0.7rem] font-medium text-sky-700 dark:text-sky-400">
          {t("inbox.message_menu.reply_to").replace(":name", sender)}
        </p>
        <p className="line-clamp-2 text-xs leading-snug text-pretty text-muted-foreground">
          {text}
        </p>
      </div>

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            className="shrink-0 text-muted-foreground"
            aria-label={t("inbox.message_menu.reply_cancel")}
            onClick={onCancel}
          >
            <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2} />
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t("inbox.message_menu.reply_cancel")}</TooltipContent>
      </Tooltip>
    </div>
  )
}

interface QuotedMessageProps {
  reply: MessageReply
  onAccent: boolean
  className?: string
}

/** Kutipan kecil di dalam gelembung. Nada warnanya mengikuti gelembung induk. */
export function QuotedMessage({ reply, onAccent, className }: QuotedMessageProps) {
  const { t } = useTrans()
  const text = quotedText(
    reply,
    t(`inbox.media.${reply.media_type ?? "document"}`),
  )

  return (
    <div
      data-testid="quoted-message"
      className={cn(
        "mb-1 rounded-md border-s-2 px-2 py-1",
        onAccent
          ? "border-white/70 bg-white/15 text-white/90"
          : "border-sky-600/70 bg-foreground/5 text-foreground/75 dark:border-sky-400/70",
        className,
      )}
    >
      <p className="line-clamp-2 text-xs leading-snug text-pretty">{text}</p>
    </div>
  )
}

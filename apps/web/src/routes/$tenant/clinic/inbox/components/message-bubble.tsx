import { HugeiconsIcon } from "@hugeicons/react"
import {
  AlertCircleIcon,
  DocumentAttachmentIcon,
  Download01Icon,
  Tick01Icon,
  TickDouble01Icon,
} from "@hugeicons/core-free-icons"

import { Bubble, BubbleContent } from "#/components/ui/bubble.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import { messageTime } from "../inbox-thread.ts"
import type { Message, MessageStatus } from "../types.ts"
import { MessageContextMenu } from "./message-context-menu.tsx"
import { QuotedMessage } from "./reply-preview.tsx"

interface MessageBubbleProps {
  message: Message
  tenant: string
  conversationId: number
  onReply: (message: Message) => void
}

/**
 * Satu gelembung utas. Pesan keluar di kanan dengan biru pekat; pesan masuk di
 * kiri dengan latar lembut berbingkai. Status kirim hanya milik pesan keluar —
 * pesan masuk tidak punya nasib yang bisa gagal di sisi kita.
 */
export function MessageBubble({
  message,
  tenant,
  conversationId,
  onReply,
}: MessageBubbleProps) {
  const { t } = useTrans()
  const isOut = message.direction === "out"
  const hasMedia = message.media_url !== null

  return (
    <MessageContextMenu
      message={message}
      tenant={tenant}
      conversationId={conversationId}
      align={isOut ? "end" : "start"}
      onReply={onReply}
    >
      <Bubble
        align={isOut ? "end" : "start"}
        variant={isOut ? "accent" : "secondary"}
        data-testid="message-bubble"
        data-direction={message.direction}
        className="max-w-full"
      >
        <BubbleContent
          className={cn(
            "text-pretty rounded-2xl shadow-sm",
            isOut ? "rounded-br-md" : "rounded-bl-md border-border/60",
            hasMedia ? "space-y-1 p-1" : "px-3 py-1.5",
          )}
        >
          {message.reply_to ? (
            <QuotedMessage
              reply={message.reply_to}
              onAccent={isOut}
              className={hasMedia ? "mx-1 mt-1" : undefined}
            />
          ) : null}
          {hasMedia ? <MessageMedia message={message} isOut={isOut} /> : null}
          {message.body.trim() !== "" ? (
            <p
              className={cn(
                "whitespace-pre-wrap leading-snug",
                hasMedia && "px-2 pt-0.5",
              )}
            >
              {message.body}
            </p>
          ) : null}
  
          <div
            className={cn(
              "flex items-center justify-end gap-1 text-[0.6rem] tabular-nums",
              hasMedia ? "px-2 pb-1" : "mt-0.5",
              isOut ? "text-white/85" : "text-muted-foreground",
            )}
          >
            {message.reaction ? (
              <span
                data-testid="message-reaction"
                className={cn(
                  "rounded-full px-1 text-[0.7rem] leading-4",
                  isOut ? "bg-white/20" : "bg-foreground/10",
                )}
              >
                {message.reaction}
              </span>
            ) : null}
            <span>{messageTime(message.sent_at)}</span>
            {isOut && message.status ? (
              <MessageStatusIndicator
                status={message.status}
                onAccent
                label={message.status_label ?? t(`inbox.status.${message.status}`)}
              />
            ) : null}
          </div>
        </BubbleContent>
      </Bubble>
    </MessageContextMenu>
  )
}

/**
 * Nasib pengiriman pesan keluar. Label teks hanya untuk pembaca layar; mata
 * cukup melihat centangnya. Status null (pesan lama) tidak dirender pemanggil.
 */
function MessageStatusIndicator({
  status,
  label,
  onAccent = false,
}: {
  status: MessageStatus
  label: string
  onAccent?: boolean
}) {
  const failed = status === "failed"

  return (
    <span
      data-testid="message-status"
      data-status={status}
      className={cn(
        "inline-flex items-center",
        failed && (onAccent ? "text-white" : "text-destructive"),
      )}
    >
      <HugeiconsIcon
        icon={
          failed
            ? AlertCircleIcon
            : status === "sent"
              ? Tick01Icon
              : TickDouble01Icon
        }
        strokeWidth={2}
        aria-hidden
        className={cn(
          "size-3",
          status === "read" &&
            (onAccent ? "text-white" : "text-sky-500 dark:text-sky-400"),
        )}
      />
      <span className="sr-only">{label}</span>
    </span>
  )
}

function MessageMedia({
  message,
  isOut,
}: {
  message: Message
  isOut: boolean
}) {
  const { t } = useTrans()
  const url = message.media_url
  if (!url) return null

  const label =
    message.media_name?.trim() || t(`inbox.media.${message.media_type ?? "document"}`)

  if (message.media_type === "image" || message.media_type === "sticker") {
    return (
      <a
        href={url}
        target="_blank"
        rel="noreferrer"
        aria-label={t("inbox.media.open")}
        className="block overflow-hidden rounded-lg"
      >
        <img
          src={url}
          alt={label}
          loading="lazy"
          className={cn(
            "max-h-64 w-auto max-w-full object-cover",
            message.media_type === "sticker" && "max-h-32",
          )}
        />
      </a>
    )
  }

  if (message.media_type === "video") {
    return (
      <video
        src={url}
        controls
        preload="metadata"
        aria-label={label}
        className={cn(
          "max-h-64 w-full rounded-lg",
          isOut ? "bg-black/20" : "bg-foreground/5",
        )}
      />
    )
  }

  if (message.media_type === "audio") {
    return (
      <audio
        src={url}
        controls
        preload="metadata"
        aria-label={label}
        className="w-full min-w-56"
      />
    )
  }

  return (
    <a
      href={url}
      target="_blank"
      rel="noreferrer"
      download={message.media_name ?? undefined}
      className={cn(
        "flex max-w-full items-center gap-2 rounded-lg px-2.5 py-2 ring-1",
        isOut
          ? "bg-white/15 text-white ring-white/25"
          : "bg-background/60 ring-foreground/10",
      )}
    >
      <span
        aria-hidden
        className={cn(
          "flex size-8 shrink-0 items-center justify-center rounded-md",
          isOut ? "bg-white/20 text-white" : "bg-muted text-muted-foreground",
        )}
      >
        <HugeiconsIcon icon={DocumentAttachmentIcon} strokeWidth={1.8} className="size-4" />
      </span>
      <span className="min-w-0 flex-1 truncate text-xs font-medium">{label}</span>
      <HugeiconsIcon
        icon={Download01Icon}
        strokeWidth={1.8}
        aria-label={t("inbox.media.download")}
        className={cn(
          "size-4 shrink-0",
          isOut ? "text-white/80" : "text-muted-foreground",
        )}
      />
    </a>
  )
}

import { Bubble, BubbleContent } from "#/components/ui/bubble.tsx"
import { cn } from "#/lib/utils.ts"
import { messageTime } from "../inbox-thread.ts"
import type { Message } from "../types.ts"

/**
 * Satu gelembung utas. Pesan keluar di kanan dengan latar tipis dari warna
 * utama; pesan masuk di kiri dengan latar muted. Status kirim hanya milik
 * pesan keluar — pesan masuk tidak punya nasib yang bisa gagal di sisi kita.
 */
export function MessageBubble({ message }: { message: Message }) {
  const isOut = message.direction === "out"

  return (
    <Bubble
      align={isOut ? "end" : "start"}
      variant={isOut ? "tinted" : "muted"}
      data-testid="message-bubble"
      data-direction={message.direction}
      className="max-w-[85%] sm:max-w-[75%]"
    >
      <BubbleContent className="text-pretty">{message.body}</BubbleContent>
      <div
        className={cn(
          "flex items-center gap-1.5 px-1 text-[0.65rem] text-muted-foreground tabular-nums",
          isOut ? "justify-end" : "justify-start",
        )}
      >
        <span>{messageTime(message.sent_at)}</span>
        {isOut ? (
          <span
            data-testid="message-status"
            className={cn(message.status === "failed" && "font-medium text-destructive")}
          >
            {message.status_label}
          </span>
        ) : null}
      </div>
    </Bubble>
  )
}

import {
  useEffect,
  useRef,
  useState,
  type KeyboardEvent,
  type MouseEvent,
  type PointerEvent,
  type ReactNode,
} from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { Add01Icon, ArrowLeft01Icon } from "@hugeicons/core-free-icons"
import { toast } from "sonner"

import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuTrigger,
} from "#/components/ui/context-menu.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import { useSendReaction } from "../hooks/use-send-reaction.ts"
import type { Message } from "../types.ts"
import { MessageInfoDialog } from "./message-info-dialog.tsx"
import { MessageMenuItems } from "./message-menu-items.tsx"

const QUICK_REACTIONS = ["👍", "❤️", "😂", "😮", "😢", "🙏"] as const
const MORE_REACTIONS = [
  "😁", "😅", "🙂", "😊", "😍", "😘", "😎", "🤔",
  "😴", "😭", "😡", "🤯", "👏", "🙌", "🔥", "🎉",
  "✅", "❌", "👌", "💪", "🤝", "🤒", "💊", "📅",
] as const

const LONG_PRESS_MS = 450
const MOVE_CANCEL_PX = 10

/** Buka menu di titik layar tertentu lewat event `contextmenu` buatan. */
function openMenuAt(element: HTMLElement, x: number, y: number) {
  element.dispatchEvent(
    new MouseEvent("contextmenu", {
      bubbles: true,
      cancelable: true,
      button: 2,
      clientX: x,
      clientY: y,
    }),
  )
}

async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    // Clipboard API butuh HTTPS; peramban lama atau http polos pakai jalur ini.
    const area = document.createElement("textarea")
    area.value = text
    area.style.position = "fixed"
    area.style.opacity = "0"
    document.body.appendChild(area)
    area.select()

    try {
      return document.execCommand("copy")
    } catch {
      return false
    } finally {
      area.remove()
    }
  }
}

interface MessageContextMenuProps {
  message: Message
  tenant: string
  conversationId: number
  align: "start" | "end"
  onReply: (message: Message) => void
  children: ReactNode
}

/**
 * Menu klik kanan / tekan-lama pada gelembung, mengikuti pola WhatsApp.
 * Sentuhan panjang membuka menu setelah 450 ms; geseran atau gulir
 * membatalkannya supaya menelusuri utas tidak memunculkan menu.
 */
export function MessageContextMenu({
  message,
  tenant,
  conversationId,
  align,
  onReply,
  children,
}: MessageContextMenuProps) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const [infoOpen, setInfoOpen] = useState(false)
  const [showMore, setShowMore] = useState(false)
  const [pendingEmoji, setPendingEmoji] = useState<string | null>(null)
  const press = useRef<{ timer: number; x: number; y: number } | null>(null)
  const suppressClick = useRef(false)
  const firstReaction = useRef<HTMLButtonElement>(null)
  const reaction = useSendReaction(tenant, conversationId)

  const clearPress = () => {
    if (press.current === null) return
    window.clearTimeout(press.current.timer)
    press.current = null
  }

  useEffect(() => {
    window.addEventListener("scroll", clearPress, true)
    return () => {
      window.removeEventListener("scroll", clearPress, true)
      clearPress()
    }
  }, [])

  const handlePointerDown = (event: PointerEvent<HTMLDivElement>) => {
    if (event.pointerType === "mouse" || event.button !== 0) return

    clearPress()
    suppressClick.current = false

    const element = event.currentTarget
    const x = event.clientX
    const y = event.clientY

    press.current = {
      x,
      y,
      timer: window.setTimeout(() => {
        press.current = null
        suppressClick.current = true
        openMenuAt(element, x, y)
      }, LONG_PRESS_MS),
    }
  }

  const handlePointerMove = (event: PointerEvent<HTMLDivElement>) => {
    const pending = press.current
    if (pending === null) return

    if (
      Math.abs(event.clientX - pending.x) > MOVE_CANCEL_PX ||
      Math.abs(event.clientY - pending.y) > MOVE_CANCEL_PX
    ) {
      clearPress()
    }
  }

  const handleClickCapture = (event: MouseEvent<HTMLDivElement>) => {
    if (!suppressClick.current) return

    suppressClick.current = false
    event.preventDefault()
    event.stopPropagation()
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const isMenuKey =
      event.key === "ContextMenu" || (event.shiftKey && event.key === "F10")
    if (!isMenuKey) return

    event.preventDefault()
    const rect = event.currentTarget.getBoundingClientRect()
    openMenuAt(
      event.currentTarget,
      rect.left + rect.width / 2,
      rect.top + rect.height / 2,
    )
  }

  const react = (emoji: string) => {
    // Gelembung optimistis belum punya id server; reaksinya akan 404.
    if (message.id < 0) return

    setPendingEmoji(emoji)
    setOpen(false)
    reaction.mutate(
      { messageId: message.id, emoji: message.reaction === emoji ? "" : emoji },
      { onSettled: () => setPendingEmoji(null) },
    )
  }

  const copy = () => {
    setOpen(false)
    void copyText(message.body).then((ok) => {
      toast[ok ? "success" : "error"](
        t(ok ? "inbox.message_menu.copy_success" : "inbox.message_menu.copy_failed"),
      )
    })
  }

  const reactionButton = (emoji: string) => {
    const busy = pendingEmoji === emoji
    const active = message.reaction === emoji
    const first = showMore
      ? emoji === MORE_REACTIONS[0]
      : emoji === QUICK_REACTIONS[0]

    return (
      <button
        key={emoji}
        ref={first ? firstReaction : undefined}
        type="button"
        aria-label={t("inbox.message_menu.react_with").replace(":emoji", emoji)}
        aria-pressed={active}
        disabled={reaction.isPending}
        data-testid={`reaction-${emoji}`}
        className={cn(
          "flex size-7 items-center justify-center rounded-md text-base leading-none outline-none transition-colors hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50",
          active && "bg-foreground/10",
        )}
        onClick={() => react(emoji)}
      >
        {busy ? <Spinner className="size-4" /> : emoji}
      </button>
    )
  }

  return (
    <>
      <ContextMenu open={open} onOpenChange={setOpen}>
        <ContextMenuTrigger asChild>
          <div
            data-testid="message-menu-trigger"
            tabIndex={0}
            aria-keyshortcuts="Shift+F10"
            className={cn(
              "relative flex w-fit max-w-[85%] flex-col rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-ring/40 sm:max-w-[75%] [-webkit-touch-callout:none]",
              align === "end" ? "self-end" : "self-start",
            )}
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={clearPress}
            onPointerCancel={clearPress}
            onPointerLeave={clearPress}
            onClickCapture={handleClickCapture}
            onKeyDown={handleKeyDown}
          >
            {children}
          </div>
        </ContextMenuTrigger>

        <ContextMenuContent className="w-60 overflow-visible p-0">
          <div className="border-b border-border/50 p-1">
            {showMore ? (
              <>
                <div className="mb-1 flex items-center justify-between px-0.5">
                  <span className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                    {t("inbox.message_menu.more_reactions")}
                  </span>
                  <button
                    type="button"
                    aria-label={t("inbox.message_menu.back")}
                    className="flex size-6 items-center justify-center rounded-md text-muted-foreground outline-none transition-colors hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring/50"
                    onClick={() => setShowMore(false)}
                  >
                    <HugeiconsIcon icon={ArrowLeft01Icon} strokeWidth={2} className="size-3.5" />
                  </button>
                </div>
                <div className="grid grid-cols-8 gap-0.5">
                  {MORE_REACTIONS.map(reactionButton)}
                </div>
              </>
            ) : (
              <div className="flex items-center justify-between">
                {QUICK_REACTIONS.map(reactionButton)}
                <button
                  type="button"
                  aria-label={t("inbox.message_menu.more")}
                  data-testid="reaction-more"
                  className="flex size-7 items-center justify-center rounded-md text-muted-foreground outline-none transition-colors hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring/50"
                  onClick={() => setShowMore(true)}
                >
                  <HugeiconsIcon icon={Add01Icon} strokeWidth={2} className="size-4" />
                </button>
              </div>
            )}
          </div>

          <MessageMenuItems
            onInfo={() => setInfoOpen(true)}
            onReply={() => onReply(message)}
            onCopy={copy}
            onReact={() => firstReaction.current?.focus()}
          />
        </ContextMenuContent>
      </ContextMenu>

      <MessageInfoDialog
        open={infoOpen}
        onOpenChange={setInfoOpen}
        message={message}
      />
    </>
  )
}

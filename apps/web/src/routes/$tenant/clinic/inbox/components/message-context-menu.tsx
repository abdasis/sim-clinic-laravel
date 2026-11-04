import {
  useEffect,
  useRef,
  useState,
  type KeyboardEvent,
  type MouseEvent,
  type PointerEvent,
  type ReactNode,
} from "react"
import { toast } from "sonner"

import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuTrigger,
} from "#/components/ui/context-menu.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import type { Message } from "../types.ts"
import { MessageInfoDialog } from "./message-info-dialog.tsx"
import { MessageMenuItems } from "./message-menu-items.tsx"

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
  align: "start" | "end"
  children: ReactNode
}

/**
 * Menu klik kanan / tekan-lama pada gelembung. Sentuhan panjang membuka menu
 * setelah 450 ms; geseran atau gulir membatalkannya supaya menelusuri utas
 * tidak memunculkan menu.
 *
 * Reaksi dan kutipan balasan tidak lagi ditawarkan: gateway internal tidak
 * mengirim keduanya, jadi menampilkannya hanya menjanjikan yang tidak terjadi.
 * Kutipan pada pesan lama tetap dirender oleh gelembungnya sendiri.
 */
export function MessageContextMenu({
  message,
  align,
  children,
}: MessageContextMenuProps) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const [infoOpen, setInfoOpen] = useState(false)
  const press = useRef<{ timer: number; x: number; y: number } | null>(null)
  const suppressClick = useRef(false)

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

  const copy = () => {
    setOpen(false)
    void copyText(message.body).then((ok) => {
      toast[ok ? "success" : "error"](
        t(ok ? "inbox.message_menu.copy_success" : "inbox.message_menu.copy_failed"),
      )
    })
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

        <ContextMenuContent className="w-60 p-1">
          <MessageMenuItems
            onInfo={() => setInfoOpen(true)}
            onCopy={copy}
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

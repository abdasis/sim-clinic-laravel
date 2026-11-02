import { useEffect, useState } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { Add01Icon, Delete02Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import {
  MAX_QUICK_REPLIES,
  appendQuickReply,
  readQuickReplies,
  removeQuickReply,
  saveQuickReplies,
  type QuickReply,
} from "./quick-replies.ts"

interface QuickReplyDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onInsert: (text: string) => void
}

/** Balasan cepat tersimpan di peramban; tidak ada endpoint untuknya. */
export function QuickReplyDialog({
  open,
  onOpenChange,
  onInsert,
}: QuickReplyDialogProps) {
  const { t } = useTrans()
  const [replies, setReplies] = useState<QuickReply[]>([])
  const [text, setText] = useState("")

  useEffect(() => {
    if (open) {
      setReplies(readQuickReplies())
      setText("")
    }
  }, [open])

  const commit = (next: QuickReply[]) => {
    setReplies(next)
    saveQuickReplies(next)
  }

  const add = () => {
    const next = appendQuickReply(replies, text)

    if (next === replies) return

    commit(next)
    setText("")
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("inbox.attach.quick.title")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("inbox.attach.quick.hint")}
          </DialogDescription>
        </DialogHeader>

        <div className="flex items-end gap-2">
          <div className="grid flex-1 gap-1.5">
            <Label htmlFor="quick-reply-text">{t("inbox.attach.quick.placeholder")}</Label>
            <Input
              id="quick-reply-text"
              value={text}
              maxLength={500}
              disabled={replies.length >= MAX_QUICK_REPLIES}
              onChange={(event) => setText(event.target.value)}
              onKeyDown={(event) => {
                if (event.key !== "Enter" || event.nativeEvent.isComposing) return
                event.preventDefault()
                add()
              }}
            />
          </div>
          <Button
            type="button"
            variant="outline"
            disabled={text.trim() === "" || replies.length >= MAX_QUICK_REPLIES}
            onClick={add}
          >
            <HugeiconsIcon icon={Add01Icon} strokeWidth={2} aria-hidden />
            {t("inbox.attach.quick.add")}
          </Button>
        </div>

        <p className="text-[0.7rem] text-muted-foreground tabular-nums">
          {t("inbox.attach.quick.limit")
            .replace(":count", String(MAX_QUICK_REPLIES))
            .replace(":used", String(replies.length))}
        </p>

        {replies.length === 0 ? (
          <p className="rounded-md border border-dashed border-border/70 px-3 py-6 text-center text-xs text-pretty text-muted-foreground">
            {t("inbox.attach.quick.empty")}
          </p>
        ) : (
          <ul className="max-h-64 space-y-1 overflow-y-auto scrollbar-thin">
            {replies.map((reply) => (
              <li key={reply.id} className="flex items-center gap-1">
                <button
                  type="button"
                  title={t("inbox.attach.quick.insert")}
                  data-testid="quick-reply-item"
                  className="min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-left text-sm outline-none transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/40"
                  onClick={() => {
                    onInsert(reply.text)
                    onOpenChange(false)
                  }}
                >
                  {reply.text}
                </button>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon-sm"
                      aria-label={t("inbox.attach.quick.delete")}
                      data-testid="quick-reply-delete"
                      onClick={() => commit(removeQuickReply(replies, reply.id))}
                    >
                      <HugeiconsIcon icon={Delete02Icon} strokeWidth={2} />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{t("inbox.attach.quick.delete")}</TooltipContent>
                </Tooltip>
              </li>
            ))}
          </ul>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t("general.close")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

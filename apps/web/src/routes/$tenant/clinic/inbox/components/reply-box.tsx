import { useId } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { SentIcon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { AttachmentMenu } from "./attachment-menu.tsx"
import { ComposerEditor } from "./composer-editor.tsx"

interface ReplyBoxProps {
  tenant: string
  conversationId: number
  value: string
  isSending: boolean
  error: string | null
  disabled?: boolean
  onChange: (value: string) => void
  onSend: () => void
  onInsertText: (text: string) => void
}

/**
 * Kotak balasan: Ctrl/Cmd+Enter mengirim, Enter biasa menambah baris. Isi
 * editor diterjemahkan ke format WhatsApp (`*tebal*`, `_miring_`, dst.)
 * sebelum sampai ke pemanggil.
 */
export function ReplyBox({
  tenant,
  conversationId,
  value,
  isSending,
  error,
  disabled,
  onChange,
  onSend,
  onInsertText,
}: ReplyBoxProps) {
  const { t } = useTrans()
  const errorId = useId()
  const canSend = value.trim() !== "" && !isSending && !disabled

  return (
    <div className="space-y-1.5">
      {error ? (
        <p
          id={errorId}
          role="alert"
          className="text-xs text-pretty text-destructive"
        >
          {error}
        </p>
      ) : null}

      <div className="rounded-lg bg-muted p-1.5 transition-shadow duration-150 focus-within:ring-2 focus-within:ring-ring/25">
        <div className="overflow-hidden rounded-md bg-card ring-1 ring-foreground/6">
          <ComposerEditor
            value={value}
            label={t("inbox.message_body")}
            placeholder={t("inbox.reply_placeholder")}
            canSend={canSend}
            disabled={disabled}
            onChange={onChange}
            onSend={onSend}
          />
        </div>

        <div className="mt-1 flex items-center justify-between gap-1 px-0.5">
          <AttachmentMenu
            tenant={tenant}
            conversationId={conversationId}
            disabled={disabled}
            onInsertText={onInsertText}
          />

          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                size="icon"
                className="size-9 shrink-0"
                aria-label={t("inbox.send")}
                aria-keyshortcuts="Control+Enter Meta+Enter"
                data-testid="send-button"
                disabled={!canSend}
                // Jaga fokus di editor: klik kirim tidak boleh memindahkan kursor
                // ke tombol, supaya balasan berikutnya bisa langsung diketik.
                onMouseDown={(event) => event.preventDefault()}
                onClick={onSend}
              >
                {isSending ? (
                  <Spinner />
                ) : (
                  <HugeiconsIcon icon={SentIcon} strokeWidth={2} />
                )}
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              {t("inbox.send")}
              <Kbd>Ctrl</Kbd>
              <Kbd>Enter</Kbd>
            </TooltipContent>
          </Tooltip>
        </div>
      </div>
    </div>
  )
}

import { useId } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { SentIcon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface ReplyBoxProps {
  value: string
  isSending: boolean
  error: string | null
  disabled?: boolean
  onChange: (value: string) => void
  onSend: () => void
}

/**
 * Kotak balasan: Enter mengirim, Shift+Enter menambah baris.
 *
 * Tingginya mengikuti isi (`field-sizing-content` di primitif Textarea), jadi
 * balasan panjang tidak tersembunyi di dalam kotak satu baris.
 */
export function ReplyBox({
  value,
  isSending,
  error,
  disabled,
  onChange,
  onSend,
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

      <div className="flex items-end gap-2">
        <Textarea
          value={value}
          onChange={(event) => onChange(event.target.value)}
          onKeyDown={(event) => {
            if (
              event.key === "Enter" &&
              !event.shiftKey &&
              !event.nativeEvent.isComposing
            ) {
              event.preventDefault()
              if (canSend) onSend()
            }
          }}
          aria-label={t("inbox.message_body")}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          data-testid="reply-textarea"
          placeholder={t("inbox.reply_placeholder")}
          disabled={disabled}
          rows={1}
          className="max-h-40 min-h-10 resize-none py-2"
        />

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              size="icon"
              className="size-10 shrink-0"
              aria-label={t("inbox.send")}
              aria-keyshortcuts="Enter"
              data-testid="send-button"
              disabled={!canSend}
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
            <Kbd>Enter</Kbd>
          </TooltipContent>
        </Tooltip>
      </div>
    </div>
  )
}

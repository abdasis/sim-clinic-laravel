import { useEffect, useRef, useState } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { CheckmarkCircle02Icon, Copy01Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { cn } from "#/lib/utils.ts"

interface WebhookUrlProps {
  url?: string | null
  copyLabel: string
  copiedLabel: string
  emptyLabel: string
  className?: string
}

/**
 * URL webhook hanya ditampilkan, tidak pernah menyertakan secret. Bila klipbor
 * diblokir browser, teksnya dipilih supaya tetap bisa disalin manual.
 */
export function WebhookUrl({
  url,
  copyLabel,
  copiedLabel,
  emptyLabel,
  className,
}: WebhookUrlProps) {
  const [copied, setCopied] = useState(false)
  const codeRef = useRef<HTMLElement>(null)
  const timerRef = useRef<number | undefined>(undefined)

  useEffect(() => () => window.clearTimeout(timerRef.current), [])

  if (!url) {
    return (
      <p className={cn("text-xs text-muted-foreground", className)}>
        {emptyLabel}
      </p>
    )
  }

  const selectText = () => {
    const node = codeRef.current
    const selection = window.getSelection()
    if (!node || !selection) return

    const range = document.createRange()
    range.selectNodeContents(node)
    selection.removeAllRanges()
    selection.addRange(range)
  }

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url)
    } catch {
      selectText()
      return
    }

    setCopied(true)
    window.clearTimeout(timerRef.current)
    timerRef.current = window.setTimeout(() => setCopied(false), 2000)
  }

  return (
    <TooltipProvider>
      <div className={cn("flex items-start gap-2", className)}>
        <code
          ref={codeRef}
          className="min-w-0 flex-1 select-all break-all rounded-md bg-muted/40 px-2.5 py-1.5 font-mono text-xs"
        >
          {url}
        </code>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-7 shrink-0"
              aria-label={copied ? copiedLabel : copyLabel}
              onClick={copy}
            >
              <HugeiconsIcon
                icon={copied ? CheckmarkCircle02Icon : Copy01Icon}
                strokeWidth={2}
                className="size-3.5"
                aria-hidden="true"
              />
            </Button>
          </TooltipTrigger>
          <TooltipContent>{copied ? copiedLabel : copyLabel}</TooltipContent>
        </Tooltip>
      </div>
    </TooltipProvider>
  )
}

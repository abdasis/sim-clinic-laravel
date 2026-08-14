import { MessageCircle } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { normalizeWhatsapp } from "./cta-link.tsx"

interface ChatWidgetProps {
  enabled: boolean
  number?: string | null
  label: string
}

/** Tombol chat mengambang. Nomornya diatur admin lewat pengaturan. */
export function ChatWidget({ enabled, number, label }: ChatWidgetProps) {
  if (!enabled || !number) return null

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          asChild
          size="icon"
          aria-label={label}
          className="fixed right-5 bottom-5 z-40 size-11 rounded-full shadow-sm transition-transform hover:scale-105 active:scale-95"
        >
          <a
            href={`https://wa.me/${normalizeWhatsapp(number)}`}
            target="_blank"
            rel="noreferrer noopener"
          >
            <MessageCircle className="size-5" />
          </a>
        </Button>
      </TooltipTrigger>
      <TooltipContent side="left">{label}</TooltipContent>
    </Tooltip>
  )
}

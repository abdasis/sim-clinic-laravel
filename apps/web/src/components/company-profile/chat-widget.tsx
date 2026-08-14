import { MessageCircle } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import type { ChatChannel } from "#/hooks/use-company-profile.ts"
import { normalizeWhatsapp } from "./cta-link.tsx"
import { useContentText } from "./locale-context.tsx"

interface ChatWidgetProps {
  channels: ChatChannel[]
  fallbackLabel: string
}

/**
 * Tombol chat mengambang. Kanal pertama yang diatur admin yang dipakai —
 * satu tombol sudah cukup, daftar panjang justru menghalangi konten.
 */
export function ChatWidget({ channels, fallbackLabel }: ChatWidgetProps) {
  const text = useContentText()
  const channel = channels[0]

  if (!channel?.url) return null

  const label = text(channel.label) ?? fallbackLabel
  // Kolom kanal chat bebas teks; admin kerap mengisi nomor, bukan tautan.
  const href =
    channel.type === "whatsapp" && !/^https?:\/\//i.test(channel.url)
      ? `https://wa.me/${normalizeWhatsapp(channel.url)}`
      : channel.url

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          asChild
          size="icon"
          aria-label={label}
          className="fixed right-5 bottom-5 z-40 size-11 rounded-full shadow-sm transition-transform hover:scale-105 active:scale-95"
        >
          <a href={href} target="_blank" rel="noreferrer noopener">
            <MessageCircle className="size-5" />
          </a>
        </Button>
      </TooltipTrigger>
      <TooltipContent side="left">{label}</TooltipContent>
    </Tooltip>
  )
}

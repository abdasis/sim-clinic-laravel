import type { DataTableMeta } from "#/types/data-table.ts"

export type ConversationChannel = "waha" | "cloud_api"
export type MessageDirection = "in" | "out"
export type MessageStatus = "sent" | "delivered" | "read" | "failed"
export type MessageMediaType = "document" | "image" | "video" | "audio" | "sticker"

export interface Conversation {
  id: number
  contact_phone: string
  contact_name: string | null
  channel: ConversationChannel
  channel_label: string | null
  last_message_at: string | null
  last_message_body: string | null
  unread_count: number
  created_at: string | null
}

export interface MessageReply {
  id: number
  body: string
  direction: MessageDirection
  media_type: MessageMediaType | null
}

export interface Message {
  id: number
  direction: MessageDirection
  direction_label: string | null
  body: string
  status: MessageStatus | null
  status_label: string | null
  provider_message_id: string | null
  media_type: MessageMediaType | null
  media_url: string | null
  media_name: string | null
  sent_at: string | null
  reaction: string | null
  reply_to: MessageReply | null
}

export interface ConversationThread extends Conversation {
  messages: Message[]
}

/** Satu halaman utas. Halaman 1 berisi pesan terbaru, bukan terlama. */
export interface ThreadPage {
  data: ConversationThread
  meta: DataTableMeta
}

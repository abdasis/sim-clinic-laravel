import { describe, expect, it } from "vitest"

import {
  flattenThreadPages,
  groupMessagesByDay,
  prependOutgoingMessage,
  replaceMessage,
} from "./inbox-thread.ts"
import type { ConversationThread, Message, ThreadPage } from "./types.ts"

function message(id: number, sentAt: string): Message {
  return {
    id,
    direction: id % 2 === 0 ? "out" : "in",
    direction_label: null,
    body: `Pesan ${id}`,
    status: "sent",
    status_label: "Terkirim",
    provider_message_id: null,
    sent_at: sentAt,
  }
}

function page(messages: Message[], current = 1, last = 1): ThreadPage {
  const conversation: ConversationThread = {
    id: 1,
    contact_phone: "628123456789",
    contact_name: "Ani",
    channel: "waha",
    channel_label: "WAHA",
    last_message_at: messages[0]?.sent_at ?? null,
    last_message_body: messages[0]?.body ?? null,
    unread_count: 0,
    created_at: null,
    messages,
  }

  return {
    data: conversation,
    meta: { current_page: current, per_page: 3, total: 5, last_page: last },
  }
}

/** Halaman 1 memuat yang terbaru, halaman 2 yang lebih lama — seperti server. */
const pages = [
  page(
    [
      message(5, "2026-11-02T09:00:00+07:00"),
      message(4, "2026-11-02T08:00:00+07:00"),
      message(3, "2026-11-02T07:00:00+07:00"),
    ],
    1,
    2,
  ),
  page(
    [
      message(2, "2026-11-01T10:00:00+07:00"),
      message(1, "2026-11-01T09:00:00+07:00"),
    ],
    2,
    2,
  ),
]

describe("flattenThreadPages", () => {
  it("membalik halaman dan isinya menjadi kronologis naik", () => {
    expect(flattenThreadPages(pages).map((item) => item.id)).toEqual([
      1, 2, 3, 4, 5,
    ])
  })
})

describe("prependOutgoingMessage", () => {
  it("menaruh balasan baru di ujung utas, bukan di puncak", () => {
    const optimistic: Message = {
      ...message(6, "2026-11-02T10:00:00+07:00"),
      id: -1,
    }

    const result = flattenThreadPages(
      prependOutgoingMessage(pages, optimistic),
    )

    expect(result.at(-1)?.id).toBe(-1)
    expect(result).toHaveLength(6)
  })
})

describe("replaceMessage", () => {
  it("mengganti pesan sementara dengan pesan asli dari server", () => {
    const optimistic: Message = {
      ...message(6, "2026-11-02T10:00:00+07:00"),
      id: -1,
    }
    const stored = message(99, "2026-11-02T10:00:01+07:00")

    const result = flattenThreadPages(
      replaceMessage(prependOutgoingMessage(pages, optimistic), -1, stored),
    )

    expect(result.at(-1)).toEqual(stored)
    expect(result.some((item) => item.id === -1)).toBe(false)
  })
})

describe("groupMessagesByDay", () => {
  it("memisahkan utas per hari tanpa mengubah urutan", () => {
    const days = groupMessagesByDay(flattenThreadPages(pages))

    expect(days).toHaveLength(2)
    expect(days[0].messages.map((item) => item.id)).toEqual([1, 2])
    expect(days[1].messages.map((item) => item.id)).toEqual([3, 4, 5])
  })
})

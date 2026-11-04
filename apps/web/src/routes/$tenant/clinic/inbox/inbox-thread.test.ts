import { createElement, type ReactNode } from "react"
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { act, renderHook, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import {
  flattenThreadPages,
  groupMessagesByDay,
  prependOutgoingMessage,
  replaceMessage,
} from "./inbox-thread.ts"
import { useInboxThread } from "./hooks/use-inbox-thread.ts"
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
    media_type: null,
    media_url: null,
    media_name: null,
    sent_at: sentAt,
    reaction: null,
    reply_to: null,
  }
}

function page(messages: Message[], current = 1, last = 1): ThreadPage {
  const conversation: ConversationThread = {
    id: 1,
    contact_phone: "628123456789",
    contact_name: "Ani",
    channel: "gateway",
    channel_label: "WhatsApp",
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

describe("useInboxThread", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
    setTranslations({ inbox: { status: { sent: "Terkirim" }, direction: { out: "Keluar" } } })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  /** Gateway baru tidak mengirim reaksi maupun kutipan, jadi payload-nya polos. */
  it("tidak lagi mengirim reaction atau reply_to_message_id", async () => {
    const fetchMock = vi.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input)

      if (url.includes("/translations")) {
        return { ok: true, status: 200, json: async () => ({ data: {} }) }
      }

      if ((init as RequestInit | undefined)?.method === "POST") {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            data: message(7, "2026-11-04T10:00:00+07:00"),
            meta: { message: "Terkirim." },
          }),
        }
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: { ...pages[0].data, messages: [] },
          meta: { current_page: 1, per_page: 30, total: 0, last_page: 1 },
        }),
      }
    })
    vi.stubGlobal("fetch", fetchMock)

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const { result } = renderHook(() => useInboxThread("klinik-uji", 1), {
      wrapper: ({ children }: { children: ReactNode }) =>
        createElement(QueryClientProvider, { client }, children),
    })

    await waitFor(() => expect(result.current.query.isSuccess).toBe(true))

    await act(async () => {
      await result.current.send.mutateAsync({ body: "Halo" })
    })

    const post = fetchMock.mock.calls.find(
      ([, init]) => (init as RequestInit | undefined)?.method === "POST",
    )

    expect(post).toBeDefined()
    expect(JSON.parse(String((post?.[1] as RequestInit).body))).toEqual({ body: "Halo" })
    expect(
      fetchMock.mock.calls.some(([input]) => String(input).includes("/reaction")),
    ).toBe(false)
  })
})

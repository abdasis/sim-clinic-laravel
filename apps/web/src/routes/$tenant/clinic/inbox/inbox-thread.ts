import { format, isToday, isYesterday, parseISO } from "date-fns"

import { formatDate } from "#/lib/format.ts"
import type { Message, ThreadPage } from "./types.ts"

const relative = new Intl.RelativeTimeFormat("id", { numeric: "auto" })

/**
 * Susun utas kronologis naik dari halaman yang datang menurun.
 *
 * Setiap halaman berisi pesan terbaru lebih dulu, dan halaman berikutnya
 * berisi yang lebih lama. Jadi urutan halaman dibalik dulu, baru isi
 * masing-masing dibalik — membalik keduanya sekaligus akan menaruh pesan
 * terbaru di puncak utas.
 */
export function flattenThreadPages(pages: ThreadPage[]): Message[] {
  return pages
    .slice()
    .reverse()
    .flatMap((page) => page.data.messages.slice().reverse())
}

/** Sisipkan pesan optimistis di puncak halaman pertama (paling baru). */
export function prependOutgoingMessage(
  pages: ThreadPage[],
  message: Message,
): ThreadPage[] {
  if (pages.length === 0) return pages

  const [first, ...rest] = pages

  return [
    {
      ...first,
      data: { ...first.data, messages: [message, ...first.data.messages] },
    },
    ...rest,
  ]
}

/** Ganti pesan sementara dengan pesan asli dari server. */
export function replaceMessage(
  pages: ThreadPage[],
  temporaryId: number,
  message: Message,
): ThreadPage[] {
  return pages.map((page) => ({
    ...page,
    data: {
      ...page.data,
      messages: page.data.messages.map((item) =>
        item.id === temporaryId ? message : item,
      ),
    },
  }))
}

export interface MessageDay {
  key: string
  label: string
  messages: Message[]
}

/** Kelompokkan utas per hari, mempertahankan urutan pesan di dalamnya. */
export function groupMessagesByDay(messages: Message[]): MessageDay[] {
  const days: MessageDay[] = []

  for (const message of messages) {
    const key = dayKey(message.sent_at)
    const last = days.at(-1)

    if (last?.key === key) {
      last.messages.push(message)
    } else {
      days.push({ key, label: dayLabel(message.sent_at), messages: [message] })
    }
  }

  return days
}

function dayKey(value: string | null): string {
  const date = parseDate(value)

  return date ? format(date, "yyyy-MM-dd") : "unknown"
}

function dayLabel(value: string | null): string {
  const date = parseDate(value)

  if (!date) return ""
  if (isToday(date)) return "Hari ini"
  if (isYesterday(date)) return "Kemarin"

  return formatDate(value)
}

/** Waktu ringkas di daftar percakapan: "5 menit lalu", "kemarin", lalu tanggal. */
export function relativeTime(
  value: string | null,
  now: Date = new Date(),
): string {
  if (!value) return ""

  const date = parseDate(value)
  if (!date) return ""

  const seconds = Math.round((date.getTime() - now.getTime()) / 1000)
  const absolute = Math.abs(seconds)

  if (absolute < 60) return relative.format(0, "second")
  if (absolute < 60 * 60) return relative.format(Math.round(seconds / 60), "minute")
  if (absolute < 60 * 60 * 24)
    return relative.format(Math.round(seconds / (60 * 60)), "hour")
  if (absolute < 60 * 60 * 24 * 7)
    return relative.format(Math.round(seconds / (60 * 60 * 24)), "day")

  return formatDate(value)
}

/** Jam pesan, mis. "09.30". */
export function messageTime(value: string | null): string {
  const date = parseDate(value)

  return date ? format(date, "HH.mm") : ""
}

function parseDate(value: string | null): Date | null {
  if (!value) return null

  const date = parseISO(value)

  return Number.isNaN(date.getTime()) ? null : date
}

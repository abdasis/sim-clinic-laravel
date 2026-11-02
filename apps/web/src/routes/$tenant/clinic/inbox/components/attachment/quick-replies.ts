export interface QuickReply {
  id: string
  text: string
}

const STORAGE_KEY = "clinic_quick_replies"
export const MAX_QUICK_REPLIES = 20

/**
 * Balasan cepat milik peramban ini, bukan milik klinik: cukup untuk kalimat
 * yang berulang, tanpa menambah tabel dan layar pengaturan.
 */
export function readQuickReplies(): QuickReply[] {
  if (typeof window === "undefined") return []

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) return []

    const parsed: unknown = JSON.parse(raw)

    return Array.isArray(parsed) ? parsed.filter(isQuickReply) : []
  } catch {
    return []
  }
}

export function saveQuickReplies(replies: QuickReply[]): void {
  if (typeof window === "undefined") return

  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(replies))
  } catch {
    // Penyimpanan penuh atau diblokir: daftar tetap hidup sampai halaman ditutup.
  }
}

export function appendQuickReply(
  replies: QuickReply[],
  text: string,
): QuickReply[] {
  const trimmed = text.trim()
  if (trimmed === "" || replies.length >= MAX_QUICK_REPLIES) return replies

  return [...replies, { id: createId(), text: trimmed }]
}

export function removeQuickReply(
  replies: QuickReply[],
  id: string,
): QuickReply[] {
  return replies.filter((reply) => reply.id !== id)
}

function isQuickReply(value: unknown): value is QuickReply {
  if (typeof value !== "object" || value === null) return false

  const candidate = value as Record<string, unknown>

  return (
    typeof candidate.id === "string" &&
    typeof candidate.text === "string" &&
    candidate.text.trim() !== ""
  )
}

function createId(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`
}

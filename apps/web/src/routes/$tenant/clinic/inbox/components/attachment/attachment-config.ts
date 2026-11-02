import type { ComponentProps } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  BarChartIcon,
  Calendar03Icon,
  Camera01Icon,
  ContactIcon,
  DocumentAttachmentIcon,
  FlashIcon,
  Image01Icon,
  Invoice01Icon,
  Mic02Icon,
  ShoppingBag01Icon,
  StickerIcon,
} from "@hugeicons/core-free-icons"

/** Jenis yang dikirim ke endpoint media (`type` di multipart). */
export type UploadKind = "document" | "image" | "video" | "audio" | "sticker"

/** Pemilih "Foto & Video" menampung dua jenis sekaligus. */
export type UploadTarget = UploadKind | "media"

export type DisabledReason = "unsupported" | "catalog" | "order"

export interface UploadRule {
  /** Nilai atribut `accept` pada input berkas. */
  accept: string
  maxBytes: number
}

const MB = 1024 * 1024

/* Batas mengikuti batas WhatsApp Cloud API per jenis media. */
export const UPLOAD_RULES: Record<UploadKind, UploadRule> = {
  document: {
    accept: ".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv",
    maxBytes: 100 * MB,
  },
  image: { accept: "image/*", maxBytes: 5 * MB },
  video: { accept: "video/mp4,video/3gpp", maxBytes: 16 * MB },
  audio: { accept: "audio/*", maxBytes: 16 * MB },
  sticker: { accept: "image/webp", maxBytes: 500 * 1024 },
}

export const PICKER_RULES: Record<UploadTarget, UploadRule> = {
  ...UPLOAD_RULES,
  media: { accept: "image/*,video/mp4,video/3gpp", maxBytes: 5 * MB },
}

export type AttachmentAction =
  | { type: "upload"; target: UploadTarget; capture?: "environment" }
  | { type: "contact" }
  | { type: "quick_reply" }
  | { type: "disabled"; reason: DisabledReason }

export interface AttachmentItem {
  key: string
  icon: ComponentProps<typeof HugeiconsIcon>["icon"]
  color: string
  action: AttachmentAction
}

/* Urutan mengikuti menu lampiran WhatsApp. Yang tidak didukung Cloud API
   tetap ditampilkan supaya staf tidak mencarinya di tempat lain. */
export const ATTACHMENT_ITEMS: AttachmentItem[] = [
  { key: "document", icon: DocumentAttachmentIcon, color: "text-sky-600 dark:text-sky-400", action: { type: "upload", target: "document" } },
  { key: "photo", icon: Image01Icon, color: "text-fuchsia-600 dark:text-fuchsia-400", action: { type: "upload", target: "media" } },
  { key: "camera", icon: Camera01Icon, color: "text-rose-600 dark:text-rose-400", action: { type: "upload", target: "media", capture: "environment" } },
  { key: "audio", icon: Mic02Icon, color: "text-amber-600 dark:text-amber-400", action: { type: "upload", target: "audio" } },
  { key: "contact", icon: ContactIcon, color: "text-emerald-600 dark:text-emerald-400", action: { type: "contact" } },
  { key: "poll", icon: BarChartIcon, color: "text-teal-600 dark:text-teal-400", action: { type: "disabled", reason: "unsupported" } },
  { key: "event", icon: Calendar03Icon, color: "text-indigo-600 dark:text-indigo-400", action: { type: "disabled", reason: "unsupported" } },
  { key: "sticker", icon: StickerIcon, color: "text-pink-600 dark:text-pink-400", action: { type: "upload", target: "sticker" } },
  { key: "catalog", icon: ShoppingBag01Icon, color: "text-orange-600 dark:text-orange-400", action: { type: "disabled", reason: "catalog" } },
  { key: "quick_reply", icon: FlashIcon, color: "text-yellow-600 dark:text-yellow-400", action: { type: "quick_reply" } },
  { key: "order", icon: Invoice01Icon, color: "text-cyan-600 dark:text-cyan-400", action: { type: "disabled", reason: "order" } },
]

// @tiptap/core hanya hadir sebagai dependensi transitif; tipenya diambil
// lewat re-export @tiptap/react supaya resolusi modulnya tetap sah.
import type { JSONContent } from "@tiptap/react"
import { renderToReactElement } from "@tiptap/static-renderer"
import StarterKit from "@tiptap/starter-kit"
import Image from "@tiptap/extension-image"
import Link from "@tiptap/extension-link"

/** Ekstensi yang sama dipakai editor CMS dan renderer publik. */
export const RICH_TEXT_EXTENSIONS = [StarterKit, Image, Link]

/** Dokumen Tiptap seperti tersimpan di kolom json. */
export type RichTextDoc = JSONContent

/**
 * Render dokumen Tiptap jadi elemen React. Sengaja bukan HTML string +
 * dangerouslySetInnerHTML: konten ini diketik admin dan tetap harus lewat
 * jalur render React yang aman.
 */
export function renderRichText(doc?: RichTextDoc | null): React.ReactNode {
  if (!doc || !doc.content) return null

  return renderToReactElement({
    content: doc as never,
    extensions: RICH_TEXT_EXTENSIONS,
  })
}

/** Cek cepat apakah dokumen benar-benar ada isinya. */
export function hasRichText(doc?: RichTextDoc | null): boolean {
  return Array.isArray(doc?.content) && doc.content.length > 0
}

import type { JSONContent } from "@tiptap/react"

/**
 * Format teks WhatsApp: `*tebal*`, `_miring_`, `~coret~`, ```monospace```,
 * plus daftar `- ` dan `1. `. Dokumen editor disimpan sebagai teks ini, bukan
 * HTML — backend mengirim isinya apa adanya ke WhatsApp.
 */
const MARK_WRAPPERS = [
  ["bold", "*", "*"],
  ["italic", "_", "_"],
  ["strike", "~", "~"],
  ["code", "```", "```"],
] as const

type MarkName = (typeof MARK_WRAPPERS)[number][0]

const INLINE_MARKS: readonly { delimiter: string; mark: MarkName }[] = [
  { delimiter: "```", mark: "code" },
  { delimiter: "*", mark: "bold" },
  { delimiter: "_", mark: "italic" },
  { delimiter: "~", mark: "strike" },
]

/** Ubah dokumen editor jadi teks yang dikirim ke WhatsApp. */
export function serializeWhatsapp(doc: JSONContent): string {
  return serializeBlocks(doc.content).trimEnd()
}

function serializeBlocks(nodes: JSONContent[] | undefined): string {
  return (nodes ?? []).map(serializeBlock).join("\n")
}

function serializeBlock(node: JSONContent): string {
  if (node.type === "bulletList") {
    return serializeList(node, () => "- ")
  }

  if (node.type === "orderedList") {
    const start = Number(node.attrs?.start ?? 1)

    return serializeList(node, (index) => `${start + index}. `)
  }

  return serializeInline(node.content)
}

function serializeList(
  node: JSONContent,
  marker: (index: number) => string,
): string {
  return (node.content ?? [])
    .map((item, index) => {
      const text = serializeBlocks(item.content)

      return text
        .split("\n")
        .map((line, lineIndex) =>
          lineIndex === 0 ? `${marker(index)}${line}` : line,
        )
        .join("\n")
    })
    .join("\n")
}

function serializeInline(nodes: JSONContent[] | undefined): string {
  return (nodes ?? [])
    .map((node) => {
      if (node.type === "hardBreak") return "\n"

      if (node.type === "text") {
        const marks = new Set((node.marks ?? []).map((mark) => mark.type))

        return MARK_WRAPPERS.reduce((text, [name, open, close]) => {
          return marks.has(name) ? `${open}${text}${close}` : text
        }, node.text ?? "")
      }

      return serializeInline(node.content)
    })
    .join("")
}

/**
 * Kebalikannya: teks WhatsApp jadi dokumen editor. Dipakai saat draft
 * dikembalikan setelah gagal kirim, atau saat halaman memulihkan isi lama.
 */
export function parseWhatsapp(text: string): JSONContent {
  const blocks: JSONContent[] = []
  let list: JSONContent | null = null
  let listKind: "bulletList" | "orderedList" | null = null

  const flush = () => {
    if (list) blocks.push(list)

    list = null
    listKind = null
  }

  for (const line of text.split("\n")) {
    const bullet = line.match(/^-\s+(.*)$/)
    const ordered = line.match(/^(\d+)\.\s+(.*)$/)

    if (bullet) {
      if (listKind !== "bulletList") {
        flush()
        listKind = "bulletList"
        list = { type: "bulletList", content: [] }
      }

      list?.content?.push(listItem(parseInline(bullet[1])))
      continue
    }

    if (ordered) {
      if (listKind !== "orderedList") {
        flush()
        listKind = "orderedList"
        list = {
          type: "orderedList",
          attrs: { start: Number(ordered[1]) },
          content: [],
        }
      }

      list?.content?.push(listItem(parseInline(ordered[2])))
      continue
    }

    flush()
    blocks.push(paragraph(parseInline(line)))
  }

  flush()

  return { type: "doc", content: blocks }
}

function listItem(content: JSONContent[]): JSONContent {
  return { type: "listItem", content: [paragraph(content)] }
}

function paragraph(content: JSONContent[]): JSONContent {
  return content.length > 0 ? { type: "paragraph", content } : { type: "paragraph" }
}

function parseInline(text: string, marks: MarkName[] = []): JSONContent[] {
  const segment = findMarkedSegment(text)

  if (!segment) return text ? [textNode(text, marks)] : []

  const nodes: JSONContent[] = []
  const before = text.slice(0, segment.start)

  if (before) nodes.push(textNode(before, marks))
  nodes.push(...parseInline(segment.inner, [...marks, segment.mark]))
  nodes.push(...parseInline(text.slice(segment.end), marks))

  return nodes
}

interface MarkedSegment {
  start: number
  end: number
  inner: string
  mark: MarkName
}

function findMarkedSegment(text: string): MarkedSegment | null {
  let best: MarkedSegment | null = null

  for (const { delimiter, mark } of INLINE_MARKS) {
    const start = text.indexOf(delimiter)
    if (start === -1) continue

    const close = text.indexOf(delimiter, start + delimiter.length)
    if (close === -1 || close === start + delimiter.length) continue

    if (!best || start < best.start) {
      best = {
        start,
        end: close + delimiter.length,
        inner: text.slice(start + delimiter.length, close),
        mark,
      }
    }
  }

  return best
}

function textNode(text: string, marks: MarkName[]): JSONContent {
  return marks.length > 0
    ? { type: "text", text, marks: marks.map((type) => ({ type })) }
    : { type: "text", text }
}

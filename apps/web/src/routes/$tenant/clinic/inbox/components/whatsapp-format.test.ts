import { describe, expect, it } from "vitest"

import { parseWhatsapp, serializeWhatsapp } from "./whatsapp-format.ts"
import type { JSONContent } from "@tiptap/react"

function doc(...block: JSONContent[]): JSONContent {
  return { type: "doc", content: block }
}

function paragraph(...content: JSONContent[]): JSONContent {
  return { type: "paragraph", content }
}

function text(value: string, ...marks: string[]): JSONContent {
  return {
    type: "text",
    text: value,
    ...(marks.length > 0
      ? { marks: marks.map((type) => ({ type })) }
      : {}),
  }
}

describe("serializeWhatsapp", () => {
  it("membungkus teks tebal dengan bintang", () => {
    expect(serializeWhatsapp(doc(paragraph(text("Halo", "bold"))))).toBe(
      "*Halo*",
    )
  })

  it("menggabungkan beberapa mark pada satu teks", () => {
    expect(
      serializeWhatsapp(
        doc(paragraph(text("Halo", "italic", "bold"), text(" dunia"))),
      ),
    ).toBe("_*Halo*_ dunia")
  })

  it("mempertahankan baris baru antar paragraf", () => {
    expect(
      serializeWhatsapp(doc(paragraph(text("Satu")), paragraph(text("Dua")))),
    ).toBe("Satu\nDua")
  })

  it("menulis daftar poin dan bernomor", () => {
    const bullet = {
      type: "bulletList",
      content: [
        { type: "listItem", content: [paragraph(text("Satu"))] },
        { type: "listItem", content: [paragraph(text("Dua"))] },
      ],
    }
    const ordered = {
      type: "orderedList",
      attrs: { start: 1 },
      content: [
        { type: "listItem", content: [paragraph(text("Pertama"))] },
        { type: "listItem", content: [paragraph(text("Kedua"))] },
      ],
    }

    expect(serializeWhatsapp(doc(bullet, ordered))).toBe(
      "- Satu\n- Dua\n1. Pertama\n2. Kedua",
    )
  })

  it("menulis monospace dengan tiga backtick", () => {
    expect(serializeWhatsapp(doc(paragraph(text("kode", "code"))))).toBe(
      "```kode```",
    )
  })

  it("mengosongkan dokumen kosong", () => {
    expect(serializeWhatsapp(doc(paragraph()))).toBe("")
  })
})

describe("parseWhatsapp", () => {
  it("membaca mark bersarang kembali jadi mark bertumpuk", () => {
    const result = parseWhatsapp("_*Halo*_ dunia")
    const nodes = result.content?.[0]?.content ?? []
    const halo = nodes.find((node) => node.text === "Halo")

    expect(halo?.marks?.map((mark) => mark.type).sort()).toEqual([
      "bold",
      "italic",
    ])
  })

  it("memisahkan baris jadi paragraf", () => {
    const result = parseWhatsapp("Satu\nDua")

    expect(result.content).toHaveLength(2)
  })

  it("mengelompokkan baris berurutan jadi satu daftar", () => {
    const result = parseWhatsapp("- Satu\n- Dua")
    const list = result.content?.[0]

    expect(list?.type).toBe("bulletList")
    expect(list?.content).toHaveLength(2)
  })

  it("bolak-balik teks tetap sama", () => {
    const source = "_*Halo*_ dunia\n- Satu\n- Dua\n```kode```"

    expect(serializeWhatsapp(parseWhatsapp(source))).toBe(source)
  })
})

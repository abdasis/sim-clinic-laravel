import { beforeEach, describe, expect, it } from "vitest"

import {
  MAX_QUICK_REPLIES,
  appendQuickReply,
  readQuickReplies,
  removeQuickReply,
  saveQuickReplies,
  type QuickReply,
} from "./quick-replies.ts"

const reply = (id: string, text: string): QuickReply => ({ id, text })

describe("appendQuickReply", () => {
  it("memangkas spasi dan menambah balasan baru", () => {
    const result = appendQuickReply([], "  Halo, ada yang bisa dibantu?  ")

    expect(result).toHaveLength(1)
    expect(result[0].text).toBe("Halo, ada yang bisa dibantu?")
  })

  it("mengabaikan teks kosong tanpa mengubah daftar", () => {
    const replies = [reply("1", "Halo")]

    expect(appendQuickReply(replies, "   ")).toBe(replies)
  })

  it("berhenti di batas jumlah balasan cepat", () => {
    const full = Array.from({ length: MAX_QUICK_REPLIES }, (_, index) =>
      reply(String(index), `Balasan ${index}`),
    )

    expect(appendQuickReply(full, "Tambahan")).toBe(full)
  })
})

describe("removeQuickReply", () => {
  it("menghapus hanya balasan dengan id yang diminta", () => {
    const replies = [reply("1", "Halo"), reply("2", "Terima kasih")]

    expect(removeQuickReply(replies, "1").map((item) => item.id)).toEqual(["2"])
  })
})

describe("penyimpanan balasan cepat", () => {
  beforeEach(() => {
    window.localStorage.clear()
  })

  it("menyimpan lalu membaca kembali daftar", () => {
    const replies = [reply("1", "Halo"), reply("2", "Sampai jumpa")]

    saveQuickReplies(replies)

    expect(readQuickReplies()).toEqual(replies)
  })

  it("mengembalikan daftar kosong untuk isi yang rusak", () => {
    window.localStorage.setItem("clinic_quick_replies", "{bukan json")

    expect(readQuickReplies()).toEqual([])
  })

  it("menyaring entri yang tidak berbentuk balasan", () => {
    window.localStorage.setItem(
      "clinic_quick_replies",
      JSON.stringify([{ id: "1", text: "Halo" }, { id: 2 }, null, { text: " " }]),
    )

    expect(readQuickReplies()).toEqual([reply("1", "Halo")])
  })
})

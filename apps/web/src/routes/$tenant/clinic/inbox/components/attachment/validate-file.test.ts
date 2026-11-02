import { describe, expect, it } from "vitest"

import {
  attachmentSizeLimit,
  formatFileSize,
  resolveUploadKind,
  validateAttachmentFile,
} from "./validate-file.ts"

function file(name: string, type: string, size = 1024): File {
  const item = new File(["x"], name, { type })
  Object.defineProperty(item, "size", { value: size })

  return item
}

describe("validateAttachmentFile", () => {
  it("menerima dokumen dengan ekstensi yang didukung", () => {
    expect(
      validateAttachmentFile("document", file("surat.pdf", "application/pdf")),
    ).toBeNull()
  })

  it("menolak dokumen dengan ekstensi asing", () => {
    expect(
      validateAttachmentFile("document", file("program.exe", "application/x-msdownload")),
    ).toBe("unsupported")
  })

  it("menolak gambar di atas 5 MB dan menerima tepat di batas", () => {
    const limit = 5 * 1024 * 1024

    expect(validateAttachmentFile("image", file("foto.jpg", "image/jpeg", limit + 1))).toBe(
      "too_large",
    )
    expect(validateAttachmentFile("image", file("foto.jpg", "image/jpeg", limit))).toBeNull()
  })

  it("memisahkan gambar dan video pada pemilih Foto & Video", () => {
    const image = file("foto.png", "image/png")
    const video = file("klip.mp4", "video/mp4")

    expect(resolveUploadKind("media", image)).toBe("image")
    expect(resolveUploadKind("media", video)).toBe("video")
    expect(resolveUploadKind("media", file("klip.mov", "video/quicktime"))).toBe("video")
    expect(validateAttachmentFile("media", file("klip.mov", "video/quicktime"))).toBe(
      "unsupported",
    )
  })

  it("hanya menerima stiker WebP maksimal 500 KB", () => {
    expect(
      validateAttachmentFile("sticker", file("stiker.webp", "image/webp", 400 * 1024)),
    ).toBeNull()
    expect(validateAttachmentFile("sticker", file("stiker.png", "image/png"))).toBe(
      "unsupported",
    )
    expect(
      validateAttachmentFile("sticker", file("stiker.webp", "image/webp", 600 * 1024)),
    ).toBe("too_large")
  })

  it("menerima audio dan menolak yang melebihi 16 MB", () => {
    expect(validateAttachmentFile("audio", file("suara.mp3", "audio/mpeg"))).toBeNull()
    expect(
      validateAttachmentFile("audio", file("suara.mp3", "audio/mpeg", 17 * 1024 * 1024)),
    ).toBe("too_large")
  })
})

describe("attachmentSizeLimit", () => {
  it("memakai batas video untuk klip pada pemilih gabungan", () => {
    expect(attachmentSizeLimit("media", file("klip.mp4", "video/mp4"))).toBe(
      16 * 1024 * 1024,
    )
  })
})

describe("formatFileSize", () => {
  it("menampilkan KB dan MB tanpa desimal", () => {
    expect(formatFileSize(500 * 1024)).toBe("500 KB")
    expect(formatFileSize(5 * 1024 * 1024)).toBe("5 MB")
  })
})

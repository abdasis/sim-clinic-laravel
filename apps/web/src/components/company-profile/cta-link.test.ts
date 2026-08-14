import { describe, expect, it } from "vitest"

import { ctaHref, internalHref, normalizeWhatsapp } from "./cta-link.tsx"

describe("normalizeWhatsapp", () => {
  it("mengubah awalan 0 jadi kode negara", () => {
    expect(normalizeWhatsapp("081234567890")).toBe("6281234567890")
  })

  it("membuang spasi, tanda hubung, dan tanda plus", () => {
    // Admin mengetik nomor dengan format bebas; wa.me hanya menerima digit.
    expect(normalizeWhatsapp("+62 812-3456-7890")).toBe("6281234567890")
    expect(normalizeWhatsapp("(021) 555 0123")).toBe("62215550123")
  })

  it("membiarkan nomor yang sudah berkode negara", () => {
    expect(normalizeWhatsapp("6281234567890")).toBe("6281234567890")
  })
})

describe("internalHref", () => {
  it("selalu relatif terhadap tenant", () => {
    expect(internalHref("demo", "/booking")).toBe("/demo/booking")
  })

  it("menerima path tanpa garis miring di depan", () => {
    expect(internalHref("demo", "booking")).toBe("/demo/booking")
  })
})

describe("ctaHref", () => {
  it("route internal jadi path bertenant", () => {
    expect(ctaHref("demo", "route_internal", "/booking")).toBe("/demo/booking")
  })

  it("tautan luar diteruskan apa adanya", () => {
    expect(ctaHref("demo", "external", "https://contoh.test/promo")).toBe(
      "https://contoh.test/promo",
    )
  })

  it("nomor telepon pada jenis whatsapp jadi tautan wa.me", () => {
    expect(ctaHref("demo", "whatsapp", "081234567890")).toBe(
      "https://wa.me/6281234567890",
    )
  })

  it("tautan wa.me yang sudah lengkap tidak dibungkus dua kali", () => {
    expect(ctaHref("demo", "whatsapp", "https://wa.me/6281234567890")).toBe(
      "https://wa.me/6281234567890",
    )
  })
})

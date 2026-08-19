import { describe, expect, it } from "vitest"

import { patientDefaults, patientSchema } from "./patient-form.tsx"

describe("patientSchema", () => {
  it("menerima jenis kelamin kosong", () => {
    const result = patientSchema.safeParse({
      ...patientDefaults,
      name: "Ibu Sinta",
      whatsapp: "081200000001",
    })

    expect(result.success).toBe(true)
  })

  it("tetap mewajibkan nama dan WhatsApp", () => {
    expect(patientSchema.safeParse(patientDefaults).success).toBe(false)
    expect(
      patientSchema.safeParse({ ...patientDefaults, name: "A", whatsapp: "" }).success,
    ).toBe(false)
  })

  it("menolak null di field opsional — sumber bug form edit", () => {
    // API mengirim null untuk field kosong; kalau nilai itu masuk ke form apa
    // adanya, submit akan gagal diam-diam. Halaman edit menyaringnya lebih
    // dulu, dan test ini menjaga alasannya tetap terlihat.
    const result = patientSchema.safeParse({
      ...patientDefaults,
      name: "Ibu Sinta",
      whatsapp: "081200000001",
      gender: null,
    })

    expect(result.success).toBe(false)
  })
})

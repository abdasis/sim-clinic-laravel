import { describe, expect, it } from "vitest"

import { templateFromLegacyMessage } from "./template-variables.ts"

describe("templateFromLegacyMessage", () => {
  it("mengubah placeholder bernama menjadi slot bernomor", () => {
    expect(templateFromLegacyMessage("Halo Kak {nama}, dari {klinik}")).toEqual({
      body: "Halo Kak {{1}}, dari {{2}}",
      params: ["nama", "klinik"],
    })
  })

  it("memakai ulang slot yang sama untuk variabel berulang", () => {
    expect(templateFromLegacyMessage("{klinik} dan {klinik}")).toEqual({
      body: "{{1}} dan {{1}}",
      params: ["klinik"],
    })
  })

  it("menerjemahkan alias lama", () => {
    expect(templateFromLegacyMessage("Hai {nama_pasien}")).toEqual({
      body: "Hai {{1}}",
      params: ["nama"],
    })
  })

  it("mengembalikan null untuk pesan kosong", () => {
    expect(templateFromLegacyMessage(null)).toBeNull()
    expect(templateFromLegacyMessage("   ")).toBeNull()
  })
})

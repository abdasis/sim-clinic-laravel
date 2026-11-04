import { describe, expect, it } from "vitest"

import { SETTINGS_ITEMS, visibleSettingsItems } from "./settings-nav.ts"

const matchesFor = (role: string, permissions: string[] | null) =>
  visibleSettingsItems(SETTINGS_ITEMS, role, permissions).map((i) => i.match)

describe("visibleSettingsItems", () => {
  it("menampilkan Chatbot untuk admin yang punya izin konten", () => {
    expect(matchesFor("admin", ["content.view"])).toContain("chatbot")
  })

  it("menyembunyikan Chatbot tanpa izin content.view", () => {
    expect(matchesFor("admin", ["staff.view"])).not.toContain("chatbot")
  })

  it("menyembunyikan Chatbot dari peran selain admin", () => {
    expect(matchesFor("doctor", ["content.view"])).not.toContain("chatbot")
  })

  it("menampilkan Log Aktivitas untuk admin yang punya izin activity_log.view", () => {
    expect(matchesFor("admin", ["activity_log.view"])).toContain("activity-logs")
  })

  it("menyembunyikan Log Aktivitas tanpa izin activity_log.view", () => {
    expect(matchesFor("admin", ["staff.view"])).not.toContain("activity-logs")
  })

  it("menyembunyikan Log Aktivitas dari peran selain admin", () => {
    expect(matchesFor("doctor", ["activity_log.view"])).not.toContain(
      "activity-logs",
    )
  })

  it("memetakan item Pengaturan ke kunci submenu sidebar", () => {
    // Bentuk yang sama dengan pemetaan di route.tsx: item tanpa `match`
    // memakai kunci induk "settings".
    const sidebarKeysFor = (role: string, permissions: string[] | null) =>
      visibleSettingsItems(SETTINGS_ITEMS, role, permissions).map((item) =>
        item.match ? `settings/${item.match}` : "settings",
      )

    expect(
      sidebarKeysFor("admin", ["content.view", "staff.view", "activity_log.view"]),
    ).toEqual([
      "settings",
      "settings/whatsapp",
      "settings/chatbot",
      "settings/users",
      "settings/preferences",
      "settings/activity-logs",
    ])

    expect(sidebarKeysFor("cashier", [])).toEqual(["settings", "settings/preferences"])
  })
})

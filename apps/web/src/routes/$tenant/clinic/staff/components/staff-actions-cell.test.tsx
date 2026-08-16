import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"
import { StaffActionsCell } from "./staff-actions-cell.tsx"
import type { StaffRow } from "../index.tsx"

const TRANSLATIONS = {
  staff: {
    edit: "Ubah Staf",
    delete: "Hapus",
    deactivate: "Nonaktifkan",
    has_history_short: "Sudah mencatat data klinik — gunakan Nonaktifkan.",
  },
  general: { edit: "Ubah", actions: "Aksi", cancel: "Batal", save: "Simpan" },
}

/**
 * Radix membuka menunya lewat pointerdown, bukan click; di jsdom keduanya
 * tidak saling memicu, jadi menu harus dibuka persis seperti yang dilakukan
 * peramban.
 */
function openMenu() {
  const trigger = screen.getByLabelText("Aksi")

  fireEvent.pointerDown(
    trigger,
    new PointerEvent("pointerdown", { bubbles: true, button: 0, ctrlKey: false }),
  )

  return trigger
}

function wrap(staff: StaffRow) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <StaffActionsCell tenant="demo" staff={staff} />
    </QueryClientProvider>,
  )
}

const base: StaffRow = {
  id: 7,
  name: "Terapis Ratna",
  email: "ratna@klinik.test",
  clinic_role: "therapist",
  clinic_role_label: "Terapis",
  status: "active",
  status_label: "Aktif",
  can_delete: true,
}

/**
 * Menu aksi staf. Yang dijaga: aksi hapus benar-benar tersedia, dan tidak
 * ditawarkan untuk staf yang sudah mencatat data klinik — menawarkannya lalu
 * ditolak server adalah janji yang tidak ditepati.
 */
describe("StaffActionsCell", () => {
  beforeEach(() => {
    setTranslations(TRANSLATIONS)
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => ({
        ok: true,
        status: 200,
        json: async () => ({ data: TRANSLATIONS }),
      })),
    )
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("menawarkan ubah dan hapus", async () => {
    wrap(base)

    openMenu()

    await waitFor(() => expect(screen.getByText("Ubah")).toBeTruthy())

    const hapus = screen.getByText("Hapus").closest("[role='menuitem']")

    expect(hapus).toBeTruthy()
    expect(hapus?.getAttribute("data-disabled")).toBeNull()
  })

  it("mematikan hapus untuk staf yang sudah mencatat data", async () => {
    wrap({ ...base, can_delete: false })

    openMenu()

    await waitFor(() => expect(screen.getByText("Hapus")).toBeTruthy())

    const item = screen.getByText("Hapus").closest("[role='menuitem']")

    expect(item?.getAttribute("data-disabled")).not.toBeNull()
  })

  it("membuka form ubah dengan data staf yang sedang dipilih", async () => {
    wrap(base)

    openMenu()
    await waitFor(() => expect(screen.getByText("Ubah")).toBeTruthy())
    fireEvent.click(screen.getByText("Ubah"))

    await waitFor(() =>
      expect(screen.getByDisplayValue("Terapis Ratna")).toBeTruthy(),
    )
    expect(screen.getByDisplayValue("ratna@klinik.test")).toBeTruthy()
  })
})

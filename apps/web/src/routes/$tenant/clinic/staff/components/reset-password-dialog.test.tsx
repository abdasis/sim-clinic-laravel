import { describe, expect, it, vi } from "vitest"
import { fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { setTranslations } from "#/utils/trans.ts"

const TRANSLATIONS = {
  general: { save: "Simpan" },
  auth: {
    reset_password: "Setel Ulang Kata Sandi",
    reset_password_desc:
      "Staf ini akan keluar dari semua perangkatnya dan harus masuk lagi.",
    new_password: "Kata Sandi Baru",
    confirm_password: "Ulangi Kata Sandi Baru",
    password_min_hint: "Minimal 8 karakter.",
    staff_password_reset: "Kata sandi staf berhasil disetel ulang.",
  },
}

setTranslations(TRANSLATIONS)

const apiPut = vi.fn((_path: string, _body: unknown) => Promise.resolve({}))

vi.mock("#/lib/api.ts", () => ({
  apiPut: (path: string, body: unknown) => apiPut(path, body),
  apiGet: () => Promise.resolve({ data: TRANSLATIONS }),
}))

vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const { ResetPasswordDialog } = await import("./reset-password-dialog.tsx")

function renderDialog() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  return render(
    <QueryClientProvider client={client}>
      <ResetPasswordDialog
        tenant="klinik-uji"
        staffId={7}
        staffName="Jasmin"
        open
        onOpenChange={() => {}}
      />
    </QueryClientProvider>,
  )
}

const type = (label: string, value: string) =>
  fireEvent.change(screen.getByLabelText(label), { target: { value } })

/**
 * Admin menyetel ulang kata sandi staf — jalur pemulihan satu-satunya,
 * karena klinik tidak punya lupa-kata-sandi lewat email.
 */
describe("ResetPasswordDialog", () => {
  it("menyebut nama stafnya, supaya tidak salah orang", () => {
    renderDialog()

    expect(screen.getByText(/Jasmin/)).toBeTruthy()
  })

  /** Akibatnya disebut terang-terangan, bukan disembunyikan. */
  it("memberi tahu bahwa stafnya akan keluar dari semua perangkat", () => {
    renderDialog()

    expect(screen.getByText(/keluar dari semua perangkatnya/)).toBeTruthy()
  })

  it("mengirim kata sandi barunya", async () => {
    apiPut.mockClear()
    renderDialog()

    type("Kata Sandi Baru", "kata-sandi-baru-9")
    type("Ulangi Kata Sandi Baru", "kata-sandi-baru-9")
    fireEvent.click(screen.getByRole("button", { name: /Setel Ulang/ }))

    await waitFor(() =>
      expect(apiPut).toHaveBeenCalledWith("/klinik-uji/clinic/staff/7/password", {
        password: "kata-sandi-baru-9",
        password_confirmation: "kata-sandi-baru-9",
      }),
    )
  })

  /** Salah ketik pada kolom yang isinya tidak terlihat ditahan sebelum dikirim. */
  it("menahan kiriman saat konfirmasinya tidak cocok", async () => {
    apiPut.mockClear()
    renderDialog()

    type("Kata Sandi Baru", "kata-sandi-baru-9")
    type("Ulangi Kata Sandi Baru", "beda-sendiri-9")
    fireEvent.click(screen.getByRole("button", { name: /Setel Ulang/ }))

    await waitFor(() => expect(apiPut).not.toHaveBeenCalled())
  })

  it("menahan kiriman saat kata sandinya terlalu pendek", async () => {
    apiPut.mockClear()
    renderDialog()

    type("Kata Sandi Baru", "pendek")
    type("Ulangi Kata Sandi Baru", "pendek")
    fireEvent.click(screen.getByRole("button", { name: /Setel Ulang/ }))

    await waitFor(() => expect(apiPut).not.toHaveBeenCalled())
  })
})

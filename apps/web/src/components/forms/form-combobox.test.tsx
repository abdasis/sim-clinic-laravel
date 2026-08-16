import { afterEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { useForm } from "react-hook-form"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { FormCombobox } from "./form-combobox.tsx"
import { Form } from "#/components/ui/form.tsx"
import { setTranslations } from "#/utils/trans.ts"

setTranslations({
  general: {
    loading: "Memuat...",
    load_failed: "Data gagal dimuat",
    no_data: "Tidak ada data",
  },
})

const OPTIONS = [
  { label: "Ibu Sinta", value: "1" },
  { label: "Pak Budi", value: "2" },
]

interface Values {
  patient_id: string
}

function Harness({
  onValue,
  ...props
}: {
  onValue?: (value: string) => void
} & Partial<React.ComponentProps<typeof FormCombobox<Values>>>) {
  const form = useForm<Values>({ defaultValues: { patient_id: "" } })

  onValue?.(form.watch("patient_id"))

  return (
    <Form {...form}>
      <FormCombobox
        control={form.control}
        name="patient_id"
        label="Pasien"
        placeholder="Cari"
        options={OPTIONS}
        {...props}
      />
    </Form>
  )
}

/**
 * Memilih pasien adalah langkah pertama tiap transaksi. Kalau klik pada daftar
 * tidak menuliskan nilainya ke form, tombol simpan terasa mati tanpa
 * penjelasan — jadi rantai klik-ke-nilai ini diuji, bukan diyakini.
 *
 * Membuka popup lewat ketikan tidak bisa diuji di jsdom: Base UI memakai
 * pointer event yang tidak disimulasikan. Yang diuji di sini rantai nilainya,
 * dengan daftar yang sudah terbuka.
 */
/** useTrans memakai React Query, jadi providernya wajib ada. */
function renderField(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>{ui}</QueryClientProvider>,
  )
}

describe("FormCombobox", () => {
  afterEach(cleanup)

  it("menuliskan pilihan ke form saat item diklik", async () => {
    const onValue = vi.fn()
    renderField(<Harness onValue={onValue} />)

    // Base UI membuka popup lewat trigger; di jsdom klik biasa sudah cukup.
    fireEvent.click(screen.getByRole("button", { name: "" }))

    const option = await screen.findByText("Ibu Sinta")
    fireEvent.click(option)

    await waitFor(() => {
      expect(onValue).toHaveBeenLastCalledWith("1")
    })
  })

  it("menyebut daftar pilihan yang gagal dimuat", async () => {
    renderField(<Harness error options={[]} />)

    fireEvent.click(screen.getByRole("button", { name: "" }))

    expect(await screen.findByText("Data gagal dimuat")).toBeTruthy()
  })
})

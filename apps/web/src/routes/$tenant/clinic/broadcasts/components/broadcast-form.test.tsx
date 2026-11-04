import { useState } from "react"
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"

import { Form } from "#/components/ui/form.tsx"
import { useForm } from "#/components/forms/use-form.ts"
import { apiPost } from "#/lib/api.ts"
import {
  BROADCAST_DEFAULTS,
  BroadcastFormFields,
  broadcastSchema,
  type BroadcastValues,
} from "./broadcast-form.tsx"
import type { BroadcastTemplate } from "./broadcast-types.ts"
import { templatePayload } from "./template-variables.ts"


/**
 * Wadah kecil yang meniru halaman pembuatan: formulir mengurus isian, state
 * template dipegang pemanggil, lalu payload dikirim lewat jalur yang sama.
 */
function Harness() {
  const form = useForm(broadcastSchema, { defaultValues: BROADCAST_DEFAULTS })
  const [template, setTemplate] = useState<BroadcastTemplate | null>(null)
  const [image, setImage] = useState<File | null>(null)
  const [patientIds, setPatientIds] = useState<number[]>([])

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((values: BroadcastValues) => {
          if (!template) return

          void apiPost("/klinik-uji/clinic/broadcasts", {
            title: values.title,
            audience: values.audience,
            template: templatePayload(template),
          })
        })}
      >
        <BroadcastFormFields
          tenant="klinik-uji"
          form={form}
          template={template}
          setTemplate={setTemplate}
          image={image}
          setImage={setImage}
          patientIds={patientIds}
          setPatientIds={setPatientIds}
          previewParams={null}
          previewLoading={false}
          serviceOptions={[]}
        />
        <button type="submit">Kirim</button>
      </form>
    </Form>
  )
}

describe("BroadcastFormFields", () => {
  beforeEach(() => {
    window.localStorage.setItem("clinic_token", "tok")
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it("mengirim body dan parameter tanpa nama/language template", async () => {
    const fetchMock = vi.fn(async (_input: unknown, init?: RequestInit) => {
      void init
      return { ok: true, status: 200, json: async () => ({ data: { id: 1 } }) }
    })
    vi.stubGlobal("fetch", fetchMock)

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <Harness />
      </QueryClientProvider>,
    )

    fireEvent.change(screen.getByLabelText(/Judul Broadcast/), {
      target: { value: "Promo November" },
    })
    fireEvent.change(screen.getByLabelText("Isi Pesan"), {
      target: { value: "Halo {{1}}!" },
    })
    fireEvent.change(screen.getByLabelText("Isi bagian 1"), {
      target: { value: "nama" },
    })
    fireEvent.click(screen.getByRole("button", { name: "Kirim" }))

    await waitFor(() => {
      const post = fetchMock.mock.calls.find(
        ([input, init]) =>
          String(input).endsWith("/clinic/broadcasts") &&
          (init as RequestInit | undefined)?.method === "POST",
      )

      expect(post).toBeDefined()

      const body = JSON.parse(String((post?.[1] as RequestInit).body))
      expect(body.template).toEqual({ body: "Halo {{1}}!", params: ["nama"] })
      expect(body.template.name).toBeUndefined()
      expect(body.template.language).toBeUndefined()
    })
  })

  it("menyesuaikan jumlah pemetaan slot dengan penanda di pesan", async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <Harness />
      </QueryClientProvider>,
    )

    fireEvent.change(screen.getByLabelText("Isi Pesan"), {
      target: { value: "Hai {{1}}, layanan {{2}} menunggumu." },
    })

    expect(await screen.findByLabelText("Isi bagian 1")).toBeTruthy()
    expect(screen.getByLabelText("Isi bagian 2")).toBeTruthy()

    fireEvent.change(screen.getByLabelText("Isi Pesan"), {
      target: { value: "Hai {{1}}." },
    })

    await waitFor(() =>
      expect(screen.queryByLabelText("Isi bagian 2")).toBeNull(),
    )
  })
})

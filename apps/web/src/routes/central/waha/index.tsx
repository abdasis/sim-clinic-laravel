import { createFileRoute } from "@tanstack/react-router"
import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"

import { Card, CardContent } from "#/components/ui/card.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormPassword } from "#/components/forms/form-password.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export const Route = createFileRoute("/central/waha/")({
  component: WahaSettingsPage,
})

interface WahaSettings {
  base_url?: string | null
  has_api_key: boolean
}

const schema = z.object({
  base_url: z.string().url(),
  // Kosong berarti pertahankan kunci yang tersimpan; server memperlakukannya
  // sama, jadi field ini tidak pernah wajib diisi ulang.
  api_key: z.string().optional(),
})

type Values = z.infer<typeof schema>

function WahaSettingsPage() {
  const { t } = useTrans()
  const qc = useQueryClient()

  const settings = useQuery({
    queryKey: ["central-waha"],
    queryFn: () => apiGet<{ data: WahaSettings }>("/central/waha"),
  })

  const form = useForm(schema, {
    defaultValues: { base_url: "", api_key: "" },
  })

  const current = settings.data?.data

  useEffect(() => {
    if (!current) return

    form.reset({ base_url: current.base_url ?? "", api_key: "" })
  }, [current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut("/central/waha", {
        base_url: values.base_url,
        api_key: values.api_key || null,
      }),
    onSuccess: () => {
      toast.success(t("waha.saved"))
      qc.invalidateQueries({ queryKey: ["central-waha"] })
      // Kuncinya tidak pernah dikirim balik, jadi isian dikosongkan lagi
      // supaya tidak terlihat seolah nilainya tersimpan di layar.
      form.setValue("api_key", "")
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <div className="max-w-2xl">
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("waha.title")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground text-pretty">
          {t("waha.hint")}
        </p>
      </div>

      <Card>
        <CardContent className="pt-6">
          {settings.isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : (
            <Form {...form}>
              <form
                onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
                className="space-y-4"
              >
                <FormInput
                  control={form.control}
                  name="base_url"
                  label={t("waha.base_url")}
                  placeholder="https://waha.klinik.test"
                  required
                />

                <FormPassword
                  control={form.control}
                  name="api_key"
                  label={t("waha.api_key")}
                  description={
                    current?.has_api_key ? t("waha.api_key_saved") : undefined
                  }
                  showLabel={t("auth.show_password")}
                  hideLabel={t("auth.hide_password")}
                />

                <FormSubmit loading={mutation.isPending}>
                  {t("general.save")}
                </FormSubmit>
              </form>
            </Form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

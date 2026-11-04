import { createFileRoute } from "@tanstack/react-router"
import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"

import { Card, CardContent } from "#/components/ui/card.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export const Route = createFileRoute("/central/whatsapp-gateway/")({
  component: WhatsappGatewayPage,
})

interface GatewaySettings {
  base_url?: string | null
}

const schema = z.object({
  base_url: z.string().url(),
})

type Values = z.infer<typeof schema>

/**
 * Alamat server gateway WhatsApp yang dipakai seluruh klinik. Satu alamat
 * untuk semua tenant, jadi hanya pengelola platform yang boleh mengubahnya.
 */
function WhatsappGatewayPage() {
  const { t } = useTrans()
  const qc = useQueryClient()

  const settings = useQuery({
    queryKey: ["central-whatsapp-gateway"],
    queryFn: () => apiGet<{ data: GatewaySettings }>("/central/whatsapp-gateway"),
  })

  const form = useForm(schema, { defaultValues: { base_url: "" } })
  const current = settings.data?.data

  useEffect(() => {
    if (!current) return

    form.reset({ base_url: current.base_url ?? "" })
  }, [current, form])

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPut("/central/whatsapp-gateway", { base_url: values.base_url }),
    onSuccess: () => {
      toast.success(t("whatsapp_gateway.saved"))
      qc.invalidateQueries({ queryKey: ["central-whatsapp-gateway"] })
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
          {t("whatsapp_gateway.title")}
        </h1>
      </div>

      <Card>
        <CardContent className="pt-6">
          {settings.isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-8 w-24" />
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
                  label={t("whatsapp_gateway.base_url")}
                  placeholder="https://gateway.klinik.test"
                  required
                  inputClassName="font-mono"
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

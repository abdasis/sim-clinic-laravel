import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Form } from "#/components/ui/form.tsx"
import { Button } from "#/components/ui/button.tsx"
import { FormAlert } from "#/components/forms/form-alert.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  ServiceFormFields,
  serviceDefaults,
  serviceSchema,
  serviceToPayload,
  type ServiceValues,
} from "./components/service-form.tsx"

export const Route = createFileRoute("/$tenant/clinic/services/new")({
  component: NewServicePage,
})

function NewServicePage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/services/new" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const form = useForm(serviceSchema, { defaultValues: serviceDefaults })

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/services", params: { tenant } })

  const mutation = useMutation({
    mutationFn: (values: ServiceValues) =>
      apiPost(`/${tenant}/clinic/services`, serviceToPayload(values)),
    onSuccess: () => {
      toast.success(t("service.created"))
      qc.invalidateQueries({ queryKey: ["services"] })
      goToList()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  useBreadcrumbTail(t("service.add"))

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("service.add")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("service.add_description")}
        </p>
      </div>

      <Form {...form}>
        <form
          onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
          className="space-y-4"
        >
          {/* Galat yang bukan milik satu field — izin ditolak, server
              bermasalah — tidak punya tempat di bawah input mana pun. */}
          <FormAlert
            message={mutation.error && !mutation.error.errors ? mutation.error.message : null}
          />
          <ServiceFormFields
            form={form}
            tenant={tenant}
            disabled={mutation.isPending}
          />
          {/* Footer menempel di bawah supaya Simpan tetap terjangkau
              walaupun formnya panjang. */}
          <div className="sticky bottom-0 -mx-4 flex items-center justify-end gap-2 border-t border-border/50 bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={goToList}>
              {t("general.cancel")}
            </Button>
            <FormSubmit loading={mutation.isPending} className="min-w-28">
              {t("general.save")}
            </FormSubmit>
          </div>
        </form>
      </Form>
    </div>
  )
}

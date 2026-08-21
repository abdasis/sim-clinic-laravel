import { useEffect } from "react"
import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Form } from "#/components/ui/form.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { FormAlert } from "#/components/forms/form-alert.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  ServiceFormFields,
  serviceDefaults,
  serviceSchema,
  serviceToPayload,
  serviceToValues,
  type ServiceRow,
  type ServiceValues,
} from "../components/service-form.tsx"

export const Route = createFileRoute("/$tenant/clinic/services/$id/edit")({
  component: EditServicePage,
})

function EditServicePage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/services/$id/edit" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const form = useForm(serviceSchema, { defaultValues: serviceDefaults })

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/services", params: { tenant } })

  const { data, isLoading } = useQuery({
    queryKey: ["services", tenant, id],
    queryFn: () =>
      apiGet<{ data: ServiceRow }>(`/${tenant}/clinic/services/${id}`),
  })

  const service = data?.data

  useEffect(() => {
    if (service) form.reset(serviceToValues(service))
  }, [service, form])

  const mutation = useMutation({
    mutationFn: (values: ServiceValues) =>
      apiPut(`/${tenant}/clinic/services/${id}`, serviceToPayload(values)),
    onSuccess: () => {
      toast.success(t("service.updated"))
      qc.invalidateQueries({ queryKey: ["services"] })
      goToList()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  useBreadcrumbTail(service?.name ?? t("service.edit"))

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("service.edit")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("service.edit_description")}
        </p>
      </div>

      {/* Formulir baru dipasang setelah datanya ada. Kalau dirender lebih
          dulu lalu di-reset, editor rich-text sempat menerima dokumen kosong
          sebagai nilai awal dan isinya tidak pernah muncul. */}
      {isLoading || !service ? (
        <div className="space-y-4">
          <Skeleton className="h-56 w-full" />
          <Skeleton className="h-40 w-full" />
        </div>
      ) : (
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            className="space-y-4"
          >
            <FormAlert
              message={
                mutation.error && !mutation.error.errors
                  ? mutation.error.message
                  : null
              }
            />
            <ServiceFormFields
              form={form}
              tenant={tenant}
              disabled={mutation.isPending}
            />
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
      )}
    </div>
  )
}

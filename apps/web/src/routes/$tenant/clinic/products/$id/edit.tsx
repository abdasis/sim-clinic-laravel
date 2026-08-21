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
  ProductFormFields,
  productDefaults,
  productSchema,
  productToPayload,
  productToValues,
  type ProductRow,
  type ProductValues,
} from "../components/product-form.tsx"

export const Route = createFileRoute("/$tenant/clinic/products/$id/edit")({
  component: EditProductPage,
})

function EditProductPage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/products/$id/edit" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const form = useForm(productSchema, { defaultValues: productDefaults })

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/products", params: { tenant } })

  const { data, isLoading } = useQuery({
    queryKey: ["products", tenant, id],
    queryFn: () =>
      apiGet<{ data: ProductRow }>(`/${tenant}/clinic/products/${id}`),
  })

  const product = data?.data

  useEffect(() => {
    if (product) form.reset(productToValues(product))
  }, [product, form])

  const mutation = useMutation({
    mutationFn: (values: ProductValues) =>
      apiPut(`/${tenant}/clinic/products/${id}`, productToPayload(values)),
    onSuccess: () => {
      toast.success(t("product.updated"))
      qc.invalidateQueries({ queryKey: ["products"] })
      goToList()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  useBreadcrumbTail(product?.name ?? t("product.edit"))

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("product.edit")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("product.edit_description")}
        </p>
      </div>

      {/* Formulir baru dipasang setelah datanya ada. Kalau dirender lebih
          dulu lalu di-reset, editor rich-text sempat menerima dokumen kosong
          sebagai nilai awal dan isinya tidak pernah muncul. */}
      {isLoading || !product ? (
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
            <ProductFormFields
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

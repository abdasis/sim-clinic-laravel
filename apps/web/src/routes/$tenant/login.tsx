import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { setAuth } from "#/lib/auth.ts"
import type { AuthUser } from "#/lib/auth.ts"

export const Route = createFileRoute("/$tenant/login")({
  component: TenantLoginPage,
})

const schema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
})

type Values = z.infer<typeof schema>

interface LoginResponse {
  data: { user: AuthUser; token: string }
  meta: { redirect_to: string }
}

function TenantLoginPage() {
  const { tenant } = useParams({ from: "/$tenant/login" })
  const { t } = useTrans()
  const form = useForm(schema, {
    defaultValues: { email: "", password: "" },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost<LoginResponse>(`/${tenant}/login`, values),
    onSuccess: (res) => {
      setAuth(res.data.token, res.data.user)
      toast.success(t("auth.login_success"))
      if (typeof window !== "undefined") {
        window.location.href = `/${tenant}/clinic`
      }
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <main className="page-wrap px-4 py-12">
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/login", params: { tenant } },
          { label: t("auth.login") },
        ]}
      />
      <Card className="mx-auto max-w-md">
        <CardHeader>
          <CardTitle>{t("auth.login")}</CardTitle>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
              className="space-y-4"
            >
              <FormInput
                control={form.control}
                name="email"
                label={t("auth.email")}
                type="email"
              />
              <FormInput
                control={form.control}
                name="password"
                label={t("auth.password")}
                type="password"
              />
              <FormSubmit loading={mutation.isPending}>
                {t("auth.login")}
              </FormSubmit>
            </form>
          </Form>
        </CardContent>
      </Card>
    </main>
  )
}

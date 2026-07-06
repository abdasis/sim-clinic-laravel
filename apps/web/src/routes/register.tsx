import { createFileRoute } from "@tanstack/react-router"
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

export const Route = createFileRoute("/register")({
  component: RegisterPage,
})

const schema = z.object({
  company_name: z.string().min(1).max(255),
  phone: z.string().min(1),
  email: z.string().email(),
  password: z
    .string()
    .min(8)
    .regex(/^(?=.*[A-Za-z])(?=.*\d).{8,}$/),
})

type Values = z.infer<typeof schema>

interface RegisterResponse {
  data: { tenant: { slug: string }; user: { email: string } }
  meta: { redirect_to: string }
}

function RegisterPage() {
  const { t } = useTrans()
  const form = useForm(schema, {
    defaultValues: { company_name: "", phone: "", email: "", password: "" },
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost<RegisterResponse>("/register", values),
    onSuccess: (res) => {
      toast.success(t("tenant.registered"))
      if (typeof window !== "undefined") {
        window.location.href = res.meta.redirect_to
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
          { label: t("general.home"), to: "/" },
          { label: t("auth.register") },
        ]}
      />
      <Card className="mx-auto max-w-md">
        <CardHeader>
          <CardTitle>{t("auth.register")}</CardTitle>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
              className="space-y-4"
            >
              <FormInput
                control={form.control}
                name="company_name"
                label={t("tenant.company_name")}
              />
              <FormInput
                control={form.control}
                name="phone"
                label={t("tenant.phone")}
              />
              <FormInput
                control={form.control}
                name="email"
                label={t("tenant.email")}
                type="email"
              />
              <FormInput
                control={form.control}
                name="password"
                label={t("auth.password")}
                type="password"
              />
              <FormSubmit loading={mutation.isPending}>
                {t("auth.register")}
              </FormSubmit>
            </form>
          </Form>
        </CardContent>
      </Card>
    </main>
  )
}

import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import {
  CalendarCheckIcon,
  DashboardSquare01Icon,
  Globe02Icon,
} from "@hugeicons/core-free-icons"

import {
  AUTH_SUBMIT_CLASS,
  AuthLayout,
} from "#/components/auth/auth-layout.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormPassword } from "#/components/forms/form-password.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
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
    <AuthLayout
      kicker={t("auth.login_kicker")}
      title={t("brand.tagline")}
      subtitle={tenant}
      points={[
        { icon: CalendarCheckIcon, label: t("brand.value_1") },
        { icon: DashboardSquare01Icon, label: t("brand.value_2") },
        { icon: Globe02Icon, label: t("brand.value_3") },
      ]}
      formHeading={t("auth.welcome_back")}
      formSubheading={t("auth.login_subtitle")}
      footerLink={{
        text: t("auth.no_account"),
        linkLabel: t("auth.register"),
        to: "/register",
      }}
    >
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
          <FormPassword
            control={form.control}
            name="password"
            label={t("auth.password")}
            showLabel={t("auth.show_password")}
            hideLabel={t("auth.hide_password")}
          />
          <FormSubmit loading={mutation.isPending} className={AUTH_SUBMIT_CLASS}>
            {t("auth.login")}
          </FormSubmit>
        </form>
      </Form>
    </AuthLayout>
  )
}

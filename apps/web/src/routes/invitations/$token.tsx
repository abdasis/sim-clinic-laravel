import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation, useQuery } from "@tanstack/react-query"
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
import { FormPassword } from "#/components/forms/form-password.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export const Route = createFileRoute("/invitations/$token")({
  component: InvitationPage,
})

const schema = z.object({
  password: z
    .string()
    .min(8)
    .regex(/^(?=.*[A-Za-z])(?=.*\d).{8,}$/),
})

type Values = z.infer<typeof schema>

interface InvitationResponse {
  data: { email: string; role: string }
}

interface AcceptResponse {
  data: { tenant: { slug: string } }
  meta: { redirect_to: string }
}

function InvitationPage() {
  const { token } = useParams({ from: "/invitations/$token" })
  const { t } = useTrans()
  const form = useForm(schema, { defaultValues: { password: "" } })

  const invitation = useQuery({
    queryKey: ["invitation", token],
    queryFn: () => apiGet<InvitationResponse>(`/invitations/${token}`),
    retry: false,
  })

  const mutation = useMutation({
    mutationFn: (values: Values) =>
      apiPost<AcceptResponse>(`/invitations/${token}/accept`, values),
    onSuccess: (res) => {
      toast.success(t("auth.password_set"))
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
    <AuthLayout
      kicker={t("auth.invitation_kicker")}
      title={t("brand.tagline")}
      subtitle={t("brand.name")}
      points={[
        { icon: CalendarCheckIcon, label: t("brand.value_1") },
        { icon: DashboardSquare01Icon, label: t("brand.value_2") },
        { icon: Globe02Icon, label: t("brand.value_3") },
      ]}
      formHeading={t("auth.set_password_title")}
      formSubheading={t("auth.set_password_subtitle")}
    >
      {/* Panel brand tetap tampil di ketiga keadaan; hanya isi form yang
          berganti — undangan rusak pun bukan halaman kosong. */}
      {invitation.isError ? (
        <p className="text-sm text-destructive">
          {t("tenant.invitation_invalid")}
        </p>
      ) : invitation.isLoading ? (
        <p className="flex items-center gap-2 text-sm text-muted-foreground">
          <Spinner />
          {t("general.loading")}
        </p>
      ) : (
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <div className="space-y-1">
              <p className="text-sm font-medium">{t("auth.email")}</p>
              <p className="text-sm text-muted-foreground">
                {invitation.data?.data.email}
              </p>
            </div>
            <FormPassword
              control={form.control}
              name="password"
              label={t("auth.password")}
              description={t("auth.password_hint")}
              showLabel={t("auth.show_password")}
              hideLabel={t("auth.hide_password")}
            />
            <FormSubmit
              loading={mutation.isPending}
              className={AUTH_SUBMIT_CLASS}
            >
              {t("general.save")}
            </FormSubmit>
          </form>
        </Form>
      )}
    </AuthLayout>
  )
}

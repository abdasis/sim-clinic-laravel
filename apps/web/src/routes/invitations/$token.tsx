import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation, useQuery } from "@tanstack/react-query"
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
    <main className="page-wrap px-4 py-12">
      <ClinicBreadcrumb
        items={[
          { label: t("general.home"), to: "/" },
          { label: t("tenant.invite") },
        ]}
      />
      <Card className="mx-auto max-w-md">
        <CardHeader>
          <CardTitle>{t("tenant.invite")}</CardTitle>
        </CardHeader>
        <CardContent>
          {invitation.isError ? (
            <p className="text-sm text-destructive">
              {t("tenant.invitation_invalid")}
            </p>
          ) : invitation.isLoading ? (
            <p className="text-sm text-muted-foreground">
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
                <FormInput
                  control={form.control}
                  name="password"
                  label={t("auth.password")}
                  type="password"
                />
                <FormSubmit loading={mutation.isPending}>
                  {t("general.save")}
                </FormSubmit>
              </form>
            </Form>
          )}
        </CardContent>
      </Card>
    </main>
  )
}

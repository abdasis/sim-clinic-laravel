import { createFileRoute, Link, useNavigate, useParams } from "@tanstack/react-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { ArrowLeft01Icon } from "@hugeicons/core-free-icons"

import { FormAlert } from "#/components/forms/form-alert.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  NAME_PATTERN,
  TEMPLATE_DEFAULTS,
  TemplateFormFields,
  templateSchema,
  templateToPayload,
  type TemplateValues,
} from "../components/template-form.tsx"
import { TemplateGuide } from "../components/template-guide.tsx"
import { TemplatePreview } from "../components/template-preview.tsx"

export const Route = createFileRoute(
  "/$tenant/clinic/broadcasts/templates/new",
)({
  component: NewTemplatePage,
})

function NewTemplatePage() {
  const { tenant } = useParams({
    from: "/$tenant/clinic/broadcasts/templates/new",
  })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const form = useForm(templateSchema, { defaultValues: TEMPLATE_DEFAULTS })

  const name = form.watch("name") ?? ""
  const headerType = form.watch("header_type")
  const headerText = form.watch("header_text") ?? ""
  const body = form.watch("body") ?? ""

  const nameValid = NAME_PATTERN.test(name.trim())
  // Judul hanya wajib saat tipenya teks; tanpa judul dan gambar sama-sama
  // dianggap lengkap.
  const headerComplete = headerType !== "TEXT" || headerText.trim().length > 0
  const bodyFilled = body.trim().length > 0
  const canSubmit = nameValid && headerComplete && bodyFilled

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/broadcasts", params: { tenant } })

  const mutation = useMutation({
    mutationFn: (values: TemplateValues) =>
      apiPost<{ data: unknown; meta?: { message?: string } }>(
        `/${tenant}/clinic/broadcasts/templates`,
        templateToPayload(values),
      ),
    onSuccess: () => {
      toast.success(t("broadcast.template_created"))
      // Daftar kelola dan pemilih broadcast berbagi awalan kunci yang sama.
      qc.invalidateQueries({ queryKey: ["broadcast-templates"] })
      goToList()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const submit = (values: TemplateValues) => {
    if (!canSubmit) return

    if (!NAME_PATTERN.test(values.name)) {
      form.setError("name", {
        type: "validate",
        message: t("broadcast.template_invalid_name"),
      })
      return
    }

    mutation.mutate(values)
  }

  useBreadcrumbTail(t("broadcast.template_new_title"))

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-5">
        <Link
          to="/$tenant/clinic/broadcasts"
          params={{ tenant }}
          className="inline-flex w-fit items-center gap-1 rounded-sm text-xs text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          <HugeiconsIcon
            icon={ArrowLeft01Icon}
            strokeWidth={2}
            className="size-3.5"
          />
          {t("broadcast.template_back")}
        </Link>
        <h1 className="mt-2 text-xl font-semibold tracking-tight">
          {t("broadcast.template_new_title")}
        </h1>
        <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground text-pretty">
          {t("broadcast.template_new_description")}
        </p>
      </div>

      <Form {...form}>
        <form onSubmit={form.handleSubmit(submit)}>
          <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_17.5rem]">
            <div className="min-w-0 space-y-4">
              <FormAlert
                message={
                  mutation.error && !mutation.error.errors
                    ? mutation.error.message
                    : null
                }
              />
              <TemplateFormFields
                form={form}
                disabled={mutation.isPending}
              />
              <TemplatePreview
                headerType={headerType}
                headerText={headerText}
                body={body}
              />
            </div>

            {/* Di mobile panduan turun setelah form; di layar lebar menempel
                di kanan agar tetap terbaca saat form digulir. */}
            <TemplateGuide
              nameValid={nameValid}
              headerComplete={headerComplete}
              bodyFilled={bodyFilled}
              className="lg:sticky lg:top-4"
            />
          </div>

          <div className="sticky bottom-0 -mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border/50 bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            {canSubmit ? null : (
              <p className="text-xs text-muted-foreground text-pretty">
                {t("broadcast.template_guide_not_ready")}
              </p>
            )}
            <div className="flex items-center gap-2">
              <Button type="button" variant="outline" onClick={goToList}>
                {t("general.cancel")}
              </Button>
              <FormSubmit
                loading={mutation.isPending}
                disabled={!canSubmit}
                className="min-w-32"
              >
                {t("broadcast.template_create")}
              </FormSubmit>
            </div>
          </div>
        </form>
      </Form>
    </div>
  )
}

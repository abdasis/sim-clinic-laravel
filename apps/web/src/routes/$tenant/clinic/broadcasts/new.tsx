import { createFileRoute, Link, useNavigate, useParams } from "@tanstack/react-router"
import { useEffect, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { ArrowLeft01Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  BROADCAST_DEFAULTS,
  BroadcastFormFields,
  broadcastSchema,
  type BroadcastValues,
} from "./components/broadcast-form.tsx"
import { BroadcastGuide } from "./components/broadcast-guide.tsx"
import type { BroadcastTemplate } from "./components/broadcast-types.ts"
import type { AudiencePreview } from "./components/recipient-preview.tsx"
import {
  BODY_MAX,
  BODY_MAX_WITH_IMAGE,
  isTemplateComplete,
  templatePayload,
} from "./components/template-variables.ts"

export const Route = createFileRoute("/$tenant/clinic/broadcasts/new")({
  component: NewBroadcastPage,
})

function NewBroadcastPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/broadcasts/new" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()

  const form = useForm(broadcastSchema, { defaultValues: BROADCAST_DEFAULTS })
  const title = form.watch("title")
  const audience = form.watch("audience")
  const days = form.watch("days")
  const serviceId = form.watch("service_id")

  const [template, setTemplate] = useState<BroadcastTemplate | null>(null)
  const [image, setImage] = useState<File | null>(null)
  const [patientIds, setPatientIds] = useState<number[]>([])
  const [previewParams, setPreviewParams] = useState<Record<string, unknown> | null>(
    null,
  )

  // Hitung penerima hanya saat pilihannya lengkap; pilihan yang masih kosong
  // tidak perlu menembak server.
  useEffect(() => {
    if (audience === "inactive" && !days) return setPreviewParams(null)
    if (audience === "service" && !serviceId) return setPreviewParams(null)
    if (audience === "selected" && patientIds.length === 0) {
      return setPreviewParams(null)
    }

    setPreviewParams({
      audience,
      days: audience === "inactive" ? days : undefined,
      service_id: audience === "service" ? serviceId : undefined,
      patient_ids: audience === "selected" ? patientIds : undefined,
    })
  }, [audience, days, serviceId, patientIds])

  const preview = useQuery({
    queryKey: ["broadcast-preview", tenant, previewParams],
    queryFn: () =>
      apiGet<{ data: AudiencePreview }>(
        `/${tenant}/clinic/broadcasts/audience-preview`,
        previewParams ?? {},
      ),
    enabled: previewParams !== null,
  })

  const services = useQuery({
    queryKey: ["services", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string }[] }>(`/${tenant}/clinic/services`, {
        per_page: 200,
      }),
    enabled: audience === "service",
  })

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/broadcasts", params: { tenant } })

  const mutation = useMutation({
    mutationFn: (values: BroadcastValues) => {
      if (!template || !isTemplateComplete(template)) {
        return Promise.reject(new Error(t("broadcast.template_required")))
      }

      const selectedDays = values.audience === "inactive" ? values.days : undefined
      const selectedService =
        values.audience === "service" && values.service_id
          ? Number(values.service_id)
          : undefined
      const selectedPatients =
        values.audience === "selected" ? patientIds : undefined
      const body = templatePayload(template)

      if (!image) {
        return apiPost<{ data: { id: number } }>(`/${tenant}/clinic/broadcasts`, {
          title: values.title,
          audience: values.audience,
          audience_params: {
            days: selectedDays,
            service_id: selectedService,
            patient_ids: selectedPatients,
          },
          template: body,
        })
      }

      // Berkas tidak muat di JSON, jadi seluruh muatannya pindah ke FormData.
      // Kunci bersarang ditulis manual karena FormData tidak mengenal objek.
      const fd = new FormData()
      fd.append("title", values.title)
      fd.append("audience", values.audience)
      fd.append("image", image)
      if (selectedDays !== undefined) {
        fd.append("audience_params[days]", String(selectedDays))
      }
      if (selectedService !== undefined) {
        fd.append("audience_params[service_id]", String(selectedService))
      }
      selectedPatients?.forEach((id) => {
        fd.append("audience_params[patient_ids][]", String(id))
      })
      fd.append("template[body]", body.body)
      body.params.forEach((param, index) => {
        fd.append(`template[params][${index}]`, param)
      })

      return apiUpload<{ data: { id: number } }>(
        `/${tenant}/clinic/broadcasts`,
        fd,
      )
    },
    onSuccess: (res) => {
      toast.success(t("broadcast.created"))
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      // Langsung ke antrean penerimanya — di sanalah pengirimannya terjadi.
      navigate({
        to: "/$tenant/clinic/broadcasts/$id",
        params: { tenant, id: String(res.data.id) },
      })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  const audienceReady =
    audience === "all" ||
    (audience === "inactive" && Boolean(days)) ||
    (audience === "service" && Boolean(serviceId)) ||
    (audience === "selected" && patientIds.length > 0)

  const titleFilled = title.trim().length > 0
  const slotsFilled = isTemplateComplete(template)
  const recipientsReady = (preview.data?.data?.count ?? 0) > 0
  const bodyFits = (template?.body.length ?? 0) <= (image ? BODY_MAX_WITH_IMAGE : BODY_MAX)
  const canSubmit = titleFilled && audienceReady && slotsFilled && bodyFits

  useBreadcrumbTail(t("broadcast.new_title"))

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
          {t("broadcast.back_to_list")}
        </Link>
        <h1 className="mt-2 text-xl font-semibold tracking-tight">
          {t("broadcast.new_title")}
        </h1>
        <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground text-pretty">
          {t("broadcast.new_description")}
        </p>
      </div>

      <Form {...form}>
        <form
          onSubmit={form.handleSubmit((values) => {
            if (!canSubmit) return
            mutation.mutate(values)
          })}
        >
          <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_17.5rem]">
            <div className="min-w-0">
              <BroadcastFormFields
                tenant={tenant}
                form={form}
                template={template}
                setTemplate={setTemplate}
                image={image}
                setImage={setImage}
                patientIds={patientIds}
                setPatientIds={setPatientIds}
                previewParams={previewParams}
                previewData={preview.data?.data}
                previewLoading={preview.isLoading}
                serviceOptions={(services.data?.data ?? []).map((service) => ({
                  label: service.name,
                  value: String(service.id),
                }))}
                disabled={mutation.isPending}
              />
            </div>

            {/* Di mobile panduan turun setelah form; di layar lebar menempel
                di kanan agar tetap terbaca saat form digulir. */}
            <BroadcastGuide
              templateSelected={template !== null}
              slotsFilled={slotsFilled}
              recipientsReady={recipientsReady}
              titleFilled={titleFilled}
              className="lg:sticky lg:top-4"
            />
          </div>

          <div className="sticky bottom-0 -mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border/50 bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            {canSubmit ? null : (
              <p className="text-xs text-muted-foreground text-pretty">
                {t("broadcast.guide_not_ready")}
              </p>
            )}
            <div className="flex items-center gap-2">
              <Button type="button" variant="outline" onClick={goToList}>
                {t("general.cancel")}
              </Button>
              <FormSubmit
                loading={mutation.isPending}
                disabled={!canSubmit}
                className="min-w-28"
              >
                {t("general.save")}
              </FormSubmit>
            </div>
          </div>
        </form>
      </Form>
    </div>
  )
}

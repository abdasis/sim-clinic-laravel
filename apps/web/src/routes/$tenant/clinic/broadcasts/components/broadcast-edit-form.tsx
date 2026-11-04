import { useEffect, useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { BroadcastFormFields, broadcastSchema, type BroadcastValues } from "./broadcast-form.tsx"
import { BroadcastGuide } from "./broadcast-guide.tsx"
import type { BroadcastDetail, BroadcastTemplate } from "./broadcast-types.ts"
import type { AudiencePreview } from "./recipient-preview.tsx"
import {
  BODY_MAX,
  BODY_MAX_WITH_IMAGE,
  isTemplateComplete,
  templatePayload,
} from "./template-variables.ts"

interface BroadcastEditFormProps {
  tenant: string
  broadcast: BroadcastDetail
}

/**
 * Formulir ubah broadcast yang belum mulai dikirim. Halaman pemanggil yang
 * mengurus judul, breadcrumb, dan gerbang `can_edit`; di sini hanya isian,
 * pratinjau penerima, dan penyimpanan.
 */
export function BroadcastEditForm({ tenant, broadcast }: BroadcastEditFormProps) {
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const id = String(broadcast.id)

  const form = useForm(broadcastSchema, {
    defaultValues: {
      title: broadcast.title ?? "",
      audience: "all",
      days: 30,
      service_id: "",
    },
  })
  const title = form.watch("title")
  const audience = form.watch("audience")
  const days = form.watch("days")
  const serviceId = form.watch("service_id")

  const [initialized, setInitialized] = useState(false)
  const [template, setTemplate] = useState<BroadcastTemplate | null>(null)
  const [image, setImage] = useState<File | null>(null)
  const [patientIds, setPatientIds] = useState<number[]>([])
  const [removeExistingImage, setRemoveExistingImage] = useState(false)
  const [previewParams, setPreviewParams] = useState<Record<string, unknown> | null>(
    null,
  )

  // Isi formulir sekali saja setelah detail tiba, supaya pengetikan pengguna
  // tidak tertimpa saat kueri memuat ulang di latar.
  useEffect(() => {
    if (initialized) return

    const params = broadcast.audience_params ?? {}

    form.reset({
      title: broadcast.title ?? "",
      audience: broadcast.audience ?? "all",
      days: params.days ?? 30,
      service_id: params.service_id ? String(params.service_id) : "",
    })
    setPatientIds(params.patient_ids ?? [])
    // Data lama bisa datang tanpa `params`; samakan bentuknya sekali di sini
    // supaya sisa formulir tidak perlu menebak.
    setTemplate(
      broadcast.template
        ? {
            body: broadcast.template.body,
            params: broadcast.template.params ?? [],
          }
        : null,
    )
    setInitialized(true)
  }, [broadcast, initialized, form])

  useEffect(() => {
    if (!initialized) return setPreviewParams(null)
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
  }, [initialized, audience, days, serviceId, patientIds])

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

  const goToDetail = () =>
    navigate({
      to: "/$tenant/clinic/broadcasts/$id",
      params: { tenant, id },
    })

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
      const removeImage = removeExistingImage && !image

      if (!image) {
        return apiPut<{ data: BroadcastDetail; meta: { message: string } }>(
          `/${tenant}/clinic/broadcasts/${id}`,
          {
            title: values.title,
            audience: values.audience,
            audience_params: {
              days: selectedDays,
              service_id: selectedService,
              patient_ids: selectedPatients,
            },
            template: body,
            ...(removeImage ? { remove_image: true } : {}),
          },
        )
      }

      // PHP tidak membaca multipart untuk PUT, jadi berkas dikirim sebagai
      // POST dengan `_method=PUT`. Kunci bersarang ditulis manual.
      const fd = new FormData()
      fd.append("_method", "PUT")
      fd.append("title", values.title)
      fd.append("audience", values.audience)
      fd.append("image", image)
      if (selectedDays !== undefined) {
        fd.append("audience_params[days]", String(selectedDays))
      }
      if (selectedService !== undefined) {
        fd.append("audience_params[service_id]", String(selectedService))
      }
      selectedPatients?.forEach((patientId) => {
        fd.append("audience_params[patient_ids][]", String(patientId))
      })
      fd.append("template[body]", body.body)
      body.params.forEach((param, index) => {
        fd.append(`template[params][${index}]`, param)
      })

      return apiUpload<{ data: BroadcastDetail; meta: { message: string } }>(
        `/${tenant}/clinic/broadcasts/${id}`,
        fd,
      )
    },
    onSuccess: () => {
      toast.success(t("broadcast.edit_saved"))
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      goToDetail()
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
  const imageSelected =
    Boolean(image) || Boolean(broadcast.image_url && !removeExistingImage)
  const bodyFits =
    (template?.body.length ?? 0) <= (imageSelected ? BODY_MAX_WITH_IMAGE : BODY_MAX)
  const canSubmit = titleFilled && audienceReady && slotsFilled && bodyFits

  return (
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
              existingImageUrl={broadcast.image_url ?? null}
              removeExistingImage={removeExistingImage}
              onRemoveExistingImage={() => {
                setImage(null)
                setRemoveExistingImage(true)
              }}
            />
          </div>

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
            <Button type="button" variant="outline" onClick={goToDetail}>
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
  )
}

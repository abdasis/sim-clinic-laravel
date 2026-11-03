import { useEffect, useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "@tanstack/react-router"
import { z } from "zod"
import { toast } from "sonner"

import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiUpload } from "#/lib/api.ts"
import { ContactPicker } from "./contact-picker.tsx"
import { RecipientPreview } from "./recipient-preview.tsx"
import type { AudiencePreview } from "./recipient-preview.tsx"
import { TemplateImageField } from "./template-image-field.tsx"
import { TemplatePicker, isTemplateComplete } from "./template-picker.tsx"
import type { BroadcastTemplate } from "./template-picker.tsx"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  title: z.string().min(1),
  audience: z.string().min(1),
  days: z.coerce.number().int().gte(1).lte(730).optional(),
  service_id: z.string().optional(),
})

type Values = z.infer<typeof schema>

const EMPTY: Values = { title: "", audience: "all", days: 30, service_id: "" }

function templatePayload(template: BroadcastTemplate) {
  const { name, language, body, header_type, params } = template
  return { name, language, body, header_type, params }
}

interface BroadcastFormDialogProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function BroadcastFormDialog({
  tenant,
  open,
  onOpenChange,
}: BroadcastFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const navigate = useNavigate()

  const form = useForm(schema, { defaultValues: EMPTY })
  const audience = form.watch("audience")
  const days = form.watch("days")
  const serviceId = form.watch("service_id")

  const [previewParams, setPreviewParams] = useState<Record<string, unknown> | null>(null)
  const [patientIds, setPatientIds] = useState<number[]>([])
  const [template, setTemplate] = useState<BroadcastTemplate | null>(null)
  const [image, setImage] = useState<File | null>(null)

  // Hitung penerima hanya saat pilihannya lengkap; select layanan yang masih
  // kosong tidak perlu menembak server.
  useEffect(() => {
    if (!open) return
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
  }, [open, audience, days, serviceId, patientIds])

  const preview = useQuery({
    queryKey: ["broadcast-preview", tenant, previewParams],
    queryFn: () =>
      apiGet<{ data: AudiencePreview }>(
        `/${tenant}/clinic/broadcasts/audience-preview`,
        previewParams ?? {},
      ),
    enabled: open && previewParams !== null,
  })

  const services = useQuery({
    queryKey: ["services", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string }[] }>(`/${tenant}/clinic/services`, {
        per_page: 200,
      }),
    enabled: open && audience === "service",
  })

  useEffect(() => {
    if (!open) return
    form.reset(EMPTY)
    setTemplate(null)
    setImage(null)
    setPatientIds([])
  }, [open, form])

  /** Ganti template berarti slot dan berkas gambar lama tidak berlaku lagi. */
  const changeTemplate = (next: BroadcastTemplate | null) => {
    const identityChanged =
      next?.name !== template?.name || next?.language !== template?.language
    setTemplate(next)
    if (identityChanged) setImage(null)
  }

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      if (!template || !isTemplateComplete(template)) {
        return Promise.reject(new Error(t("broadcast.template_required")))
      }

      const days = values.audience === "inactive" ? values.days : undefined
      const serviceId =
        values.audience === "service" && values.service_id
          ? Number(values.service_id)
          : undefined
      const selected = values.audience === "selected" ? patientIds : undefined
      const body = templatePayload(template)

      if (!image) {
        return apiPost<{ data: { id: number } }>(`/${tenant}/clinic/broadcasts`, {
          title: values.title,
          audience: values.audience,
          audience_params: { days, service_id: serviceId, patient_ids: selected },
          template: body,
        })
      }

      // Berkas tidak muat di JSON, jadi seluruh muatannya pindah ke FormData.
      // Kunci bersarang ditulis manual karena FormData tidak mengenal objek.
      const fd = new FormData()
      fd.append("title", values.title)
      fd.append("audience", values.audience)
      fd.append("image", image)
      if (days !== undefined) fd.append("audience_params[days]", String(days))
      if (serviceId !== undefined) {
        fd.append("audience_params[service_id]", String(serviceId))
      }
      selected?.forEach((id) => {
        fd.append("audience_params[patient_ids][]", String(id))
      })
      fd.append("template[name]", body.name)
      fd.append("template[language]", body.language)
      fd.append("template[body]", body.body)
      fd.append("template[header_type]", body.header_type ?? "")
      body.params.forEach((param, index) => {
        fd.append(`template[params][${index}]`, param)
      })

      return apiUpload<{ data: { id: number } }>(`/${tenant}/clinic/broadcasts`, fd)
    },
    onSuccess: (res) => {
      toast.success(t("broadcast.created"))
      qc.invalidateQueries({ queryKey: ["broadcasts"] })
      onOpenChange(false)
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

  const templateReady = template !== null && isTemplateComplete(template)
  const needsImage = template?.header_type === "IMAGE"

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] gap-0 overflow-hidden p-0 sm:max-w-lg">
        <DialogHeader className="border-b border-border/50 p-4">
          <DialogTitle>{t("broadcast.add")}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((values) => {
              if (!templateReady) return
              mutation.mutate(values)
            })}
            className="flex min-h-0 flex-col"
          >
            {/* Wadah gulir CSS biasa, bukan ScrollArea Radix: viewport-nya
                memakai height 100% terhadap induk yang tingginya auto. */}
            <div className="max-h-[62dvh] min-h-0 flex-1 overflow-y-auto">
              <div className="space-y-4 p-4">
                <FormInput
                  control={form.control}
                  name="title"
                  label={t("broadcast.title_field")}
                  required
                />

                <FormSelect
                  control={form.control}
                  name="audience"
                  label={t("broadcast.audience_label")}
                  options={[
                    { label: t("broadcast.audience.all"), value: "all" },
                    { label: t("broadcast.audience.inactive"), value: "inactive" },
                    { label: t("broadcast.audience.service"), value: "service" },
                    { label: t("broadcast.audience.selected"), value: "selected" },
                  ]}
                />

                {audience === "selected" ? (
                  <ContactPicker
                    tenant={tenant}
                    value={patientIds}
                    onChange={setPatientIds}
                    enabled={open}
                  />
                ) : null}

                {audience === "inactive" ? (
                  <FormInput
                    control={form.control}
                    name="days"
                    label={t("broadcast.days")}
                    type="number"
                    min={1}
                    max={730}
                    required
                    inputClassName="tabular-nums"
                  />
                ) : null}

                {audience === "service" ? (
                  <FormSelect
                    control={form.control}
                    name="service_id"
                    label={t("service.title")}
                    options={(services.data?.data ?? []).map((service) => ({
                      label: service.name,
                      value: String(service.id),
                    }))}
                  />
                ) : null}

                {/* Daftar penerimanya tampil sebelum tombol simpan ditekan —
                    broadcast ke ratusan orang tidak boleh salah sasaran. */}
                {previewParams !== null ? (
                  <RecipientPreview
                    data={preview.data?.data}
                    isLoading={preview.isLoading}
                  />
                ) : null}

                <div className="space-y-2 rounded-md border border-border/50 p-3">
                  <p className="text-sm font-medium">
                    {t("broadcast.template_section")}
                  </p>
                  <TemplatePicker
                    tenant={tenant}
                    value={template}
                    onChange={changeTemplate}
                    enabled={open}
                    idPrefix="broadcast-template"
                  />
                  {needsImage ? (
                    <TemplateImageField
                      file={image}
                      onChange={setImage}
                      hint={
                        image
                          ? t("broadcast.image_hint")
                          : t("broadcast.template_header_image_hint")
                      }
                    />
                  ) : null}
                </div>
              </div>
            </div>

            <DialogFooter className="border-t border-border/50 p-4">
              <FormSubmit loading={mutation.isPending} disabled={!templateReady}>
                {t("general.save")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

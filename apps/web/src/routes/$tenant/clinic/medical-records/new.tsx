import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useCallback, useState } from "react"
import { useFieldArray } from "react-hook-form"
import { useMutation, useQuery } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"
import { Trash2Icon } from "lucide-react"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { PhotoUploader, type SelectedPhoto } from "#/components/medical-photos/photo-uploader.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export const Route = createFileRoute("/$tenant/clinic/medical-records/new")({
  component: NewMedicalRecordPage,
})

const schema = z.object({
  booking_id: z.string().min(1),
  subjective: z.string().optional(),
  objective: z.string().optional(),
  assessment: z.string().optional(),
  plan: z.string().optional(),
  treatments: z.array(
    z.object({ service_id: z.string().min(1), notes: z.string().optional() }),
  ),
})

type Values = z.infer<typeof schema>

interface BookingRow {
  id: number
  patient_name?: string
  service_name?: string
  status: string
}
interface ServiceRow {
  id: number
  name: string
}

function NewMedicalRecordPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/medical-records/new" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const [photos, setPhotos] = useState<SelectedPhoto[]>([])
  const handlePhotos = useCallback((p: SelectedPhoto[]) => setPhotos(p), [])

  const form = useForm(schema, {
    defaultValues: {
      booking_id: "",
      subjective: "",
      objective: "",
      assessment: "",
      plan: "",
      treatments: [],
    },
  })
  const treatments = useFieldArray({ control: form.control, name: "treatments" })

  const bookings = useQuery({
    queryKey: ["bookings", tenant, "done"],
    queryFn: () =>
      apiGet<{ data: BookingRow[] }>(`/${tenant}/clinic/bookings`, { per_page: 100 }),
  })
  const services = useQuery({
    queryKey: ["services", tenant, "catalog"],
    queryFn: () =>
      apiGet<{ data: ServiceRow[] }>(`/${tenant}/clinic/services`, { per_page: 100 }),
  })

  const doneBookings = (bookings.data?.data ?? []).filter((b) => b.status === "done")
  const bookingOptions = doneBookings.map((b) => ({
    value: String(b.id),
    label: `#${b.id} · ${b.patient_name ?? "-"} · ${b.service_name ?? "-"}`,
  }))
  const serviceOptions = (services.data?.data ?? []).map((s) => ({
    value: String(s.id),
    label: s.name,
  }))

  const mutation = useMutation({
    mutationFn: async (values: Values) => {
      const res = await apiPost<{ data: { id: number } }>(
        `/${tenant}/clinic/medical-records`,
        {
          booking_id: Number(values.booking_id),
          subjective: values.subjective,
          objective: values.objective,
          assessment: values.assessment,
          plan: values.plan,
        },
      )
      const recordId = res.data.id
      for (const treatment of values.treatments) {
        await apiPost(`/${tenant}/clinic/medical-records/${recordId}/treatments`, {
          service_id: Number(treatment.service_id),
          notes: treatment.notes,
        })
      }
      for (const photo of photos) {
        const fd = new FormData()
        fd.append("file", photo.file)
        fd.append("type", photo.type)
        await apiUpload(`/${tenant}/clinic/medical-records/${recordId}/photos`, fd)
      }
      return recordId
    },
    onSuccess: () => {
      toast.success(t("medical_record.created"))
      navigate({ to: "/$tenant/clinic/medical-records", params: { tenant } })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("medical_record.title") },
          { label: t("general.create") },
        ]}
      />
      <h1 className="mb-4 text-xl font-semibold">{t("medical_record.add")}</h1>

      <Form {...form}>
        <form
          onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
          className="space-y-4"
        >
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("medical_record.booking")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <FormSelect
                control={form.control}
                name="booking_id"
                label={t("medical_record.booking")}
                placeholder={t("medical_record.booking")}
                options={bookingOptions}
              />
              <FormTextarea control={form.control} name="subjective" label={t("medical_record.subjective")} />
              <FormTextarea control={form.control} name="objective" label={t("medical_record.objective")} />
              <FormTextarea control={form.control} name="assessment" label={t("medical_record.assessment")} />
              <FormTextarea control={form.control} name="plan" label={t("medical_record.plan")} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex-row items-center justify-between">
              <CardTitle className="text-base">{t("medical_record.treatments")}</CardTitle>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => treatments.append({ service_id: "", notes: "" })}
              >
                {t("general.create")}
              </Button>
            </CardHeader>
            <CardContent className="space-y-3">
              {treatments.fields.map((field, index) => (
                <div key={field.id} className="flex items-start gap-2">
                  <div className="flex-1 space-y-2">
                    <FormSelect
                      control={form.control}
                      name={`treatments.${index}.service_id`}
                      label={t("booking.service")}
                      placeholder={t("booking.service")}
                      options={serviceOptions}
                    />
                    <FormTextarea
                      control={form.control}
                      name={`treatments.${index}.notes`}
                      label={t("booking.notes")}
                      rows={2}
                    />
                  </div>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="mt-6"
                    aria-label={t("general.delete")}
                    onClick={() => treatments.remove(index)}
                  >
                    <Trash2Icon />
                  </Button>
                </div>
              ))}
              {treatments.fields.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
              ) : null}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("medical_record.photos")}</CardTitle>
            </CardHeader>
            <CardContent>
              <PhotoUploader onChange={handlePhotos} />
            </CardContent>
          </Card>

          <FormSubmit loading={mutation.isPending}>{t("general.save")}</FormSubmit>
        </form>
      </Form>
    </div>
  )
}

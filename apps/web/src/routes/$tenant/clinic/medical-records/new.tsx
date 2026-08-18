import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useCallback, useState } from "react"
import { useFieldArray } from "react-hook-form"
import { useMutation, useQuery } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"
import { Trash2Icon } from "lucide-react"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormCombobox } from "#/components/forms/form-combobox.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { PhotoUploader, type SelectedPhoto } from "#/components/medical-photos/photo-uploader.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export const Route = createFileRoute("/$tenant/clinic/medical-records/new")({
  component: NewMedicalRecordPage,
  // Dari daftar booking selesai, kunjungannya sudah dipilih lebih dulu.
  validateSearch: (search: Record<string, unknown>) => ({
    booking: search.booking ? String(search.booking) : undefined,
  }),
})

const schema = z.object({
  booking_id: z.string().min(1),
  anamnesis: z.string().optional(),
  skincare_history: z.string().optional(),
  allergy_history: z.string().optional(),
  treatments: z.array(
    z.object({ service_id: z.string().min(1), notes: z.string().optional() }),
  ),
})

type Values = z.infer<typeof schema>

interface BookingRow {
  id: number
  patient_name?: string
  service_name?: string
  start_at?: string | null
  has_medical_record?: boolean
  status: string
}
interface ServiceRow {
  id: number
  name: string
}

function NewMedicalRecordPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/medical-records/new" })
  const { booking: bookingFromUrl } = Route.useSearch()
  const { t } = useTrans()
  const navigate = useNavigate()
  const [photos, setPhotos] = useState<SelectedPhoto[]>([])
  const handlePhotos = useCallback((p: SelectedPhoto[]) => setPhotos(p), [])

  const form = useForm(schema, {
    defaultValues: {
      booking_id: bookingFromUrl ?? "",
      anamnesis: "",
      skincare_history: "",
      allergy_history: "",
      treatments: [],
    },
  })
  const treatments = useFieldArray({ control: form.control, name: "treatments" })

  // Status disaring di server, bukan setelah data sampai. Dulu halaman ini
  // mengambil 100 kunjungan terbaru lalu membuang yang belum selesai di sisi
  // klien — di klinik yang ramai, seratus kunjungan terbaru bisa sama sekali
  // tidak berisi yang berstatus selesai, dan daftarnya kosong tanpa sebab
  // yang kelihatan.
  const bookings = useQuery({
    queryKey: ["bookings", tenant, "done"],
    queryFn: () =>
      apiGet<{ data: BookingRow[] }>(`/${tenant}/clinic/bookings`, {
        per_page: 100,
        filter: { status: "done" },
        sort: "start_at",
        direction: "desc",
      }),
  })
  const services = useQuery({
    queryKey: ["services", tenant, "catalog"],
    queryFn: () =>
      apiGet<{ data: ServiceRow[] }>(`/${tenant}/clinic/services`, { per_page: 100 }),
  })

  // Kunjungan yang catatannya sudah ditulis tetap ditolak server, jadi lebih
  // jujur kalau tidak ditawarkan sejak awal daripada ditolak setelah dokter
  // selesai mengetik.
  const doneBookings = (bookings.data?.data ?? []).filter(
    (b) => b.status === "done" && !b.has_medical_record,
  )
  const bookingOptions = doneBookings.map((b) => ({
    value: String(b.id),
    // Nama pasien didahulukan: yang dicari dokter adalah pasiennya, nomor
    // kunjungan cuma pembeda saat satu pasien datang lebih dari sekali.
    label: `${b.patient_name ?? "-"} · ${b.service_name ?? "-"} · #${b.id}`,
  }))
  const selectedBookingId = form.watch("booking_id")
  const selectedPatientName = doneBookings.find(
    (b) => String(b.id) === selectedBookingId,
  )?.patient_name

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
          anamnesis: values.anamnesis,
          skincare_history: values.skincare_history,
          allergy_history: values.allergy_history,
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
    onSuccess: (recordId) => {
      toast.success(t("medical_record.created"))
      navigate({
        to: "/$tenant/clinic/medical-records/$recordId",
        params: { tenant, recordId: String(recordId) },
      })
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  useBreadcrumbTail(t("medical_record.add"))
  return (
    <div>
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
              {/* Combobox, bukan select: daftarnya bisa ratusan kunjungan dan
                  yang dicari dokter adalah satu nama pasien di antaranya. */}
              <FormCombobox
                control={form.control}
                name="booking_id"
                label={t("medical_record.booking")}
                placeholder={t("general.search")}
                options={bookingOptions}
                required
                loading={bookings.isLoading}
                error={bookings.isError}
                emptyLabel={t("medical_record.no_open_booking")}
                description={t("medical_record.booking_hint")}
              />
              {/* Nama pasien tidak diketik ulang: ia melekat pada kunjungan
                  yang dipilih, jadi salah ketik di sini mustahil terjadi. */}
              {selectedPatientName ? (
                <div className="flex items-baseline gap-2 rounded-md border border-border/60 bg-muted/40 px-3 py-2">
                  <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t("medical_record.name")}
                  </span>
                  <span className="text-sm font-medium">{selectedPatientName}</span>
                </div>
              ) : null}
              <FormTextarea
                control={form.control}
                name="anamnesis"
                label={t("medical_record.anamnesis")}
                rows={5}
              />
              <div className="grid gap-4 sm:grid-cols-2">
                <FormTextarea
                  control={form.control}
                  name="skincare_history"
                  label={t("medical_record.skincare_history")}
                />
                <FormTextarea
                  control={form.control}
                  name="allergy_history"
                  label={t("medical_record.allergy_history")}
                />
              </div>
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

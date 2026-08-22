import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useCallback, useEffect, useState } from "react"
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
  // Tipe baliknya ditulis dengan kunci opsional supaya pemanggil boleh
  // menyebut salah satunya saja; tanpa itu setiap Link wajib menuliskan
  // kedua kunci, termasuk yang tidak dipakainya.
  validateSearch: (
    search: Record<string, unknown>,
  ): { booking?: string; patient?: string } => ({
    booking: search.booking ? String(search.booking) : undefined,
    // Dari riwayat satu pasien: daftar kunjungannya ikut dipersempit ke
    // pasien itu, supaya dokter tidak perlu mencari di antara ratusan
    // kunjungan milik orang lain.
    patient: search.patient ? String(search.patient) : undefined,
  }),
})

const schema = z.object({
  // Kunjungan opsional sejak rekam medis boleh ditulis untuk pasien yang
  // datang tanpa janji; pasiennya yang kini wajib (issue #319).
  patient_id: z.string().min(1),
  booking_id: z.string().optional(),
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
  patient_id: number
  patient_name?: string
  service_name?: string
  start_at?: string | null
  has_medical_record?: boolean
  status: string
}
interface PatientRow {
  id: number
  name: string
  whatsapp?: string | null
}
interface ServiceRow {
  id: number
  name: string
}

function NewMedicalRecordPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/medical-records/new" })
  const { booking: bookingFromUrl, patient: patientFromUrl } = Route.useSearch()
  const { t } = useTrans()
  const navigate = useNavigate()
  const [photos, setPhotos] = useState<SelectedPhoto[]>([])
  const handlePhotos = useCallback((p: SelectedPhoto[]) => setPhotos(p), [])

  const form = useForm(schema, {
    defaultValues: {
      patient_id: patientFromUrl ?? "",
      booking_id: bookingFromUrl ?? "",
      anamnesis: "",
      skincare_history: "",
      allergy_history: "",
      treatments: [],
    },
  })
  const treatments = useFieldArray({ control: form.control, name: "treatments" })

  const selectedPatientId = form.watch("patient_id")

  const patients = useQuery({
    queryKey: ["patients", tenant, "picker"],
    queryFn: () =>
      apiGet<{ data: PatientRow[] }>(`/${tenant}/clinic/patients`, { per_page: 100 }),
  })

  // Status disaring di server, bukan setelah data sampai. Dulu halaman ini
  // mengambil 100 kunjungan terbaru lalu membuang yang belum selesai di sisi
  // klien — di klinik yang ramai, seratus kunjungan terbaru bisa sama sekali
  // tidak berisi yang berstatus selesai, dan daftarnya kosong tanpa sebab
  // yang kelihatan.
  //
  // Daftarnya juga mengikuti pasien yang sedang dipilih. Server menolak
  // kunjungan milik orang lain, jadi menawarkannya di sini hanya menyiapkan
  // penolakan setelah dokter selesai mengetik.
  const bookingPatientId = selectedPatientId || patientFromUrl
  const bookings = useQuery({
    queryKey: ["bookings", tenant, "done", bookingPatientId ?? "all"],
    queryFn: () =>
      apiGet<{ data: BookingRow[] }>(`/${tenant}/clinic/bookings`, {
        per_page: 100,
        filter: { status: "done", patient_id: bookingPatientId },
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

  // Masuk dari kunjungan selesai berarti pasiennya sudah tertentu. Dokter
  // tidak perlu menyebutkannya lagi — dan menyebut yang berbeda justru
  // ditolak server, karena catatan akan mendarat di berkas orang lain.
  useEffect(() => {
    if (selectedPatientId || !selectedBookingId) return

    const owner = (bookings.data?.data ?? []).find(
      (b) => String(b.id) === selectedBookingId,
    )?.patient_id

    if (owner) form.setValue("patient_id", String(owner))
  }, [selectedPatientId, selectedBookingId, bookings.data, form])

  // Kunjungan yang tidak ada lagi di daftar ikut gugur — entah karena
  // pasiennya diganti, entah karena catatannya keburu ditulis orang lain.
  // Diperiksa dari daftar yang sudah selesai dimuat, bukan dari selisih id:
  // saat pasien berganti, kunjungan lama sama sekali tidak ikut terambil,
  // jadi membandingkan pemiliknya tidak akan pernah menemukan apa pun.
  useEffect(() => {
    if (!selectedBookingId || bookings.isFetching || !bookings.data) return

    const stillOffered = bookings.data.data.some(
      (b) => String(b.id) === selectedBookingId && !b.has_medical_record,
    )

    if (!stillOffered) form.setValue("booking_id", "")
  }, [selectedBookingId, bookings.data, bookings.isFetching, form])

  const patientOptions = (patients.data?.data ?? []).map((p) => ({
    value: String(p.id),
    label: p.whatsapp ? `${p.name} · ${p.whatsapp}` : p.name,
  }))
  const selectedPatientName = (patients.data?.data ?? []).find(
    (p) => String(p.id) === selectedPatientId,
  )?.name

  const serviceOptions = (services.data?.data ?? []).map((s) => ({
    value: String(s.id),
    label: s.name,
  }))

  const mutation = useMutation({
    mutationFn: async (values: Values) => {
      const res = await apiPost<{ data: { id: number } }>(
        `/${tenant}/clinic/medical-records`,
        {
          patient_id: Number(values.patient_id),
          // Dihilangkan sama sekali saat kosong: mengirim null membuat
          // aturan required_without di server tidak pernah kena.
          ...(values.booking_id ? { booking_id: Number(values.booking_id) } : {}),
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
              <CardTitle className="text-base">{t("medical_record.visit")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Pasien lebih dulu, dan wajib: catatan klinis selalu milik
                  seseorang, sementara kunjungan terjadwal belum tentu ada. */}
              <FormCombobox
                control={form.control}
                name="patient_id"
                label={t("medical_record.patient")}
                placeholder={t("general.search")}
                options={patientOptions}
                required
                loading={patients.isLoading}
                error={patients.isError}
                emptyLabel={t("general.no_data")}
                description={t("medical_record.patient_hint")}
              />
              {/* Combobox, bukan select: daftarnya bisa ratusan kunjungan dan
                  yang dicari dokter adalah satu nama pasien di antaranya. */}
              <FormCombobox
                control={form.control}
                name="booking_id"
                label={t("medical_record.booking_optional")}
                placeholder={t("general.search")}
                options={bookingOptions}
                loading={bookings.isLoading}
                error={bookings.isError}
                emptyLabel={
                  selectedPatientId
                    ? t("medical_record.no_open_booking")
                    : t("medical_record.booking_pick_patient")
                }
                description={t("medical_record.booking_hint")}
              />
              {/* Penegasan siapa yang sedang dicatat; salah pilih pasien di
                  rekam medis jauh lebih mahal daripada salah ketik. */}
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
              <CardTitle className="text-base">
                {t("medical_record.photos_before_after")}
              </CardTitle>
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

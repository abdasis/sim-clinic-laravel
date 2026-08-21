import { useEffect } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import type { SelectOption } from "#/components/forms/form-select.tsx"
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormSwitch } from "#/components/forms/form-switch.tsx"
import {
  offsetLabel,
  useReminderSetting,
} from "#/routes/$tenant/clinic/bookings/components/use-reminder-setting.ts"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface OptionRow {
  id: number
  name: string
  clinic_role?: string
}

interface OverlapWarning {
  booking_id: number
  patient_name: string
  start_at: string
  end_at: string
}

interface BookingResponse {
  data: { id: number; status: string }
  meta?: { overlap_warnings?: OverlapWarning[] }
}

const schema = z.object({
  patient_id: z.string().min(1),
  service_id: z.string().min(1),
  assignee_id: z.string().min(1),
  start_at: z.string().min(1),
  end_at: z.string().min(1),
  notes: z.string().optional(),
  remind_booking: z.boolean(),
})

type Values = z.infer<typeof schema>

const DOCTOR_ROLES = ["doctor", "therapist"]

function toOptions(rows: OptionRow[]): SelectOption[] {
  return rows.map((row) => ({ label: row.name, value: String(row.id) }))
}

export interface BookingFormValues {
  id: number
  patient_id: number
  service_id: number
  assignee_id: number
  start_at: string
  end_at: string
  notes?: string | null
  remind_booking?: boolean
  has_medical_record?: boolean
}

interface BookingFormDialogProps {
  tenant: string
  /** Diisi untuk mode ubah; kosong berarti mode tambah. */
  bookingId?: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

const emptyValues: Values = {
  patient_id: "",
  service_id: "",
  assignee_id: "",
  start_at: "",
  end_at: "",
  notes: "",
  remind_booking: false,
}

export function BookingFormDialog({
  tenant,
  bookingId,
  open,
  onOpenChange,
}: BookingFormDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const isEdit = bookingId !== undefined

  // Daftar jadwal hanya mengirim data ringkas; detail lengkap termasuk
  // has_medical_record hanya tersedia lewat endpoint show.
  const detail = useQuery({
    queryKey: ["bookings", tenant, bookingId],
    queryFn: () =>
      apiGet<{ data: BookingFormValues }>(
        `/${tenant}/clinic/bookings/${bookingId}`,
      ),
    enabled: open && isEdit,
  })

  const booking = detail.data?.data

  // Rekam medis terikat pada pasiennya, jadi pasien dikunci setelah ditulis.
  const patientLocked = booking?.has_medical_record === true
  const form = useForm(schema, { defaultValues: emptyValues })

  const reminder = useReminderSetting(tenant, open)
  const reminderActive = reminder.data?.data.is_active === true
  const reminderOffset = offsetLabel(reminder.data?.data.offset_minutes ?? 60)

  // Nilai awal ditahan sampai setelan pengingat diketahui. Kalau tidak,
  // saklarnya sempat tampil menyala di klinik yang justru mematikan fitur ini
  // — persis kebalikan dari keterangan di bawahnya.
  useEffect(() => {
    if (!open || !reminder.isSuccess) return

    form.reset(
      booking
        ? {
            patient_id: String(booking.patient_id),
            service_id: String(booking.service_id),
            assignee_id: String(booking.assignee_id),
            start_at: booking.start_at,
            end_at: booking.end_at,
            notes: booking.notes ?? "",
            remind_booking: booking.remind_booking ?? false,
          }
        : { ...emptyValues, remind_booking: reminderActive },
    )
  }, [open, booking, reminder.isSuccess, reminderActive, form])

  const patients = useQuery({
    queryKey: ["patients", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: OptionRow[] }>(`/${tenant}/clinic/patients`, {
        per_page: 100,
      }),
    enabled: open,
  })
  const services = useQuery({
    queryKey: ["services", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: OptionRow[] }>(`/${tenant}/clinic/services`, {
        per_page: 100,
      }),
    enabled: open,
  })
  const staff = useQuery({
    queryKey: ["staff", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: OptionRow[] }>(`/${tenant}/clinic/staff`, {
        per_page: 100,
      }),
    enabled: open,
  })

  const assignees = (staff.data?.data ?? []).filter((row) =>
    DOCTOR_ROLES.includes(row.clinic_role ?? ""),
  )

  const mutation = useMutation({
    mutationFn: (values: Values) => {
      const payload = {
        patient_id: Number(values.patient_id),
        service_id: Number(values.service_id),
        assignee_id: Number(values.assignee_id),
        start_at: values.start_at,
        end_at: values.end_at,
        notes: values.notes || undefined,
        remind_booking: values.remind_booking,
      }

      return isEdit
        ? apiPut<BookingResponse>(`/${tenant}/clinic/bookings/${bookingId}`, payload)
        : apiPost<BookingResponse>(`/${tenant}/clinic/bookings`, payload)
    },
    onSuccess: (res) => {
      toast.success(isEdit ? t("booking.updated") : t("booking.created"))
      const warnings = res.meta?.overlap_warnings ?? []
      if (warnings.length > 0) {
        toast.warning(t("clinic.overlap_warning"), {
          description: warnings
            .map((w) => `${w.patient_name} (${w.start_at} – ${w.end_at})`)
            .join(", "),
        })
      }
      qc.invalidateQueries({ queryKey: ["bookings-schedule"] })
      qc.invalidateQueries({ queryKey: ["bookings"] })
      onOpenChange(false)
      form.reset()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? t("booking.edit") : t("booking.add")}</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
            className="space-y-4"
          >
            <FormSelect
              control={form.control}
              name="patient_id"
              label={t("booking.patient")}
              placeholder={t("general.search")}
              options={toOptions(patients.data?.data ?? [])}
              disabled={patientLocked}
              description={patientLocked ? t("booking.patient_locked_note") : undefined}
            />
            <FormSelect
              control={form.control}
              name="service_id"
              label={t("booking.service")}
              placeholder={t("general.search")}
              options={toOptions(services.data?.data ?? [])}
            />
            <FormSelect
              control={form.control}
              name="assignee_id"
              label={t("booking.assignee")}
              placeholder={t("general.search")}
              options={toOptions(assignees)}
            />
            <FormDatePicker
              control={form.control}
              name="start_at"
              label={t("booking.start_at")}
              withTime
            />
            <FormDatePicker
              control={form.control}
              name="end_at"
              label={t("booking.end_at")}
              withTime
            />
            <FormTextarea
              control={form.control}
              name="notes"
              label={t("booking.notes")}
            />
            <FormSwitch
              control={form.control}
              name="remind_booking"
              label={t("booking.remind_booking")}
              description={
                reminderActive
                  ? `${t("booking.remind_booking_hint")} ${reminderOffset}.`
                  : t("booking.remind_booking_inactive")
              }
              disabled={!reminderActive}
            />
            <DialogFooter>
              <FormSubmit loading={mutation.isPending}>
                {t("general.save")}
              </FormSubmit>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

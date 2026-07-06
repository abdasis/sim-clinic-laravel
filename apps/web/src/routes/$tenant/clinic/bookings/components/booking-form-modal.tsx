import { useState } from "react"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { z } from "zod"
import { toast } from "sonner"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "#/components/ui/dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { Button } from "#/components/ui/button.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import type { SelectOption } from "#/components/forms/form-select.tsx"
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
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
})

type Values = z.infer<typeof schema>

const DOCTOR_ROLES = ["doctor", "therapist"]

function toOptions(rows: OptionRow[]): SelectOption[] {
  return rows.map((row) => ({ label: row.name, value: String(row.id) }))
}

export function BookingFormModal({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)
  const qc = useQueryClient()
  const form = useForm(schema, {
    defaultValues: {
      patient_id: "",
      service_id: "",
      assignee_id: "",
      start_at: "",
      end_at: "",
    },
  })

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
    mutationFn: (values: Values) =>
      apiPost<BookingResponse>(`/${tenant}/clinic/bookings`, {
        patient_id: Number(values.patient_id),
        service_id: Number(values.service_id),
        assignee_id: Number(values.assignee_id),
        start_at: values.start_at,
        end_at: values.end_at,
      }),
    onSuccess: (res) => {
      toast.success(t("booking.created"))
      const warnings = res.meta?.overlap_warnings ?? []
      if (warnings.length > 0) {
        toast.warning(t("clinic.overlap_warning"), {
          description: warnings
            .map((w) => `${w.patient_name} (${w.start_at} – ${w.end_at})`)
            .join(", "),
        })
      }
      qc.invalidateQueries({ queryKey: ["bookings-schedule"] })
      setOpen(false)
      form.reset()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button>{t("booking.add")}</Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("booking.add")}</DialogTitle>
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

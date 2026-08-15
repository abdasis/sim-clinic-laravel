import { useEffect, useState } from "react"
import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "#/components/ui/alert-dialog.tsx"
import { Form } from "#/components/ui/form.tsx"
import { Button } from "#/components/ui/button.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  PatientFormFields,
  patientSchema,
  patientDefaults,
  type PatientValues,
} from "../components/patient-form.tsx"

interface UpdatePatientResponse {
  meta?: { duplicate_warning?: boolean; duplicate_patient_id?: number }
}

export const Route = createFileRoute("/$tenant/clinic/patients/$id/edit")({
  component: EditPatientPage,
})

function EditPatientPage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/patients/$id/edit" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const form = useForm(patientSchema, { defaultValues: patientDefaults })
  const [showDuplicate, setShowDuplicate] = useState(false)

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/patients", params: { tenant } })

  const { data } = useQuery({
    queryKey: ["patients", tenant, id],
    queryFn: () =>
      apiGet<{ data: PatientValues }>(`/${tenant}/clinic/patients/${id}`),
  })

  useEffect(() => {
    if (data?.data) {
      form.reset({ ...patientDefaults, ...withoutNulls(data.data) })
    }
  }, [data, form])

  const mutation = useMutation({
    mutationFn: (values: PatientValues) =>
      apiPut<UpdatePatientResponse>(`/${tenant}/clinic/patients/${id}`, values),
    onSuccess: (res) => {
      toast.success(t("patient.updated"))
      qc.invalidateQueries({ queryKey: ["patients"] })

      // Nomor ganda hanya diperingatkan; perubahan tetap tersimpan.
      if (res.meta?.duplicate_warning) {
        setShowDuplicate(true)

        return
      }

      goToList()
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
          { label: tenant, to: "/$tenant/clinic/patients", params: { tenant } },
          { label: t("clinic.clinic") },
          {
            label: t("patient.title"),
            to: "/$tenant/clinic/patients",
            params: { tenant },
          },
          { label: t("patient.edit") },
        ]}
      />
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">{t("patient.edit")}</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("patient.edit_description")}
        </p>
      </div>

      <Form {...form}>
        <form
          onSubmit={form.handleSubmit((values) =>
            // Gender boleh kosong; kirim undefined supaya lolos aturan
            // `nullable|in:...` di backend, bukan string kosong.
            mutation.mutate({ ...values, gender: values.gender || undefined }),
          )}
          className="space-y-4"
        >
          <PatientFormFields control={form.control} />
          {/* Footer menempel di bawah supaya Simpan tetap terjangkau
              walaupun formnya panjang. */}
          <div className="sticky bottom-0 -mx-4 flex items-center justify-end gap-2 border-t border-border/50 bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="button" variant="outline" onClick={goToList}>
              {t("general.cancel")}
            </Button>
            <FormSubmit loading={mutation.isPending} className="min-w-28">
              {t("general.save")}
            </FormSubmit>
          </div>
        </form>
      </Form>

      <AlertDialog open={showDuplicate} onOpenChange={setShowDuplicate}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("patient.duplicate_title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("patient.duplicate_body")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogAction onClick={goToList}>
              {t("general.ok")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/**
 * Field opsional pulang sebagai `null` dari API, sedangkan skemanya string.
 * Tanpa penyeragaman ini, pasien yang jenis kelaminnya kosong akan gagal
 * disimpan tanpa pesan apa pun — validasinya menolak null di field yang tidak
 * menampilkan error karena memang opsional.
 */
function withoutNulls(values: PatientValues): Partial<PatientValues> {
  return Object.fromEntries(
    Object.entries(values).filter(([, value]) => value !== null && value !== undefined),
  ) as Partial<PatientValues>
}

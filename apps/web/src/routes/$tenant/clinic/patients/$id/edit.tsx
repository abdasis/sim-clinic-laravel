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
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
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
      form.reset({ ...patientDefaults, ...data.data })
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
      <Card>
        <CardHeader>
          <CardTitle>{t("patient.edit")}</CardTitle>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit((v) => mutation.mutate(v))}
              className="space-y-4"
            >
              <PatientFormFields control={form.control} />
              <FormSubmit loading={mutation.isPending}>
                {t("general.save")}
              </FormSubmit>
            </form>
          </Form>
        </CardContent>
      </Card>

      <AlertDialog open={showDuplicate} onOpenChange={setShowDuplicate}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("patient.duplicate_title")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("patient.duplicate_warning")}
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

import { useState } from "react"
import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { Form } from "#/components/ui/form.tsx"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { useForm, applyServerErrors } from "#/components/forms/use-form.ts"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "#/components/ui/alert-dialog.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  PatientFormFields,
  patientSchema,
  patientDefaults,
  type PatientValues,
} from "./components/patient-form.tsx"

export const Route = createFileRoute("/$tenant/clinic/patients/new")({
  component: NewPatientPage,
})

interface PatientStoreResponse {
  data: { id: number }
  meta?: { duplicate_warning?: boolean; duplicate_patient_id?: number }
}

function NewPatientPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/patients/new" })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [showDuplicate, setShowDuplicate] = useState(false)
  const form = useForm(patientSchema, { defaultValues: patientDefaults })

  const goToList = () =>
    navigate({ to: "/$tenant/clinic/patients", params: { tenant } })

  const mutation = useMutation({
    mutationFn: (values: PatientValues) =>
      apiPost<PatientStoreResponse>(`/${tenant}/clinic/patients`, values),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["patients"] })
      if (res.meta?.duplicate_warning) {
        setShowDuplicate(true)
        return
      }
      toast.success(t("patient.created"))
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
          { label: t("patient.add") },
        ]}
      />
      <Card>
        <CardHeader>
          <CardTitle>{t("patient.add")}</CardTitle>
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

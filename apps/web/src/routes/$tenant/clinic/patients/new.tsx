import { useState } from "react"
import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { Form } from "#/components/ui/form.tsx"
import { Button } from "#/components/ui/button.tsx"
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
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
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

  useBreadcrumbTail(t("patient.add"))
  return (
    <div>
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">{t("patient.add")}</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {t("patient.add_description")}
        </p>
      </div>

      <Form {...form}>
        <form
          onSubmit={form.handleSubmit((values) =>
            // Gender boleh kosong; kirim undefined supaya lolos aturan
            // `nullable|in:...` di backend, bukan string kosong.
            mutation.mutate({
              ...values,
              gender: values.gender || undefined,
              // "" dari select berarti tanpa pembawa; backend menerima null.
              referred_by: values.referred_by ? Number(values.referred_by) : null,
            } as never),
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

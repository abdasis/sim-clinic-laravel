import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { z } from "zod"

import { FormSubmit } from "#/components/forms/form-submit.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { applyServerErrors, useForm } from "#/components/forms/use-form.ts"
import { Button } from "#/components/ui/button.tsx"
import { Form } from "#/components/ui/form.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPatch } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

const schema = z.object({
  subjective: z.string().optional(),
  objective: z.string().optional(),
  assessment: z.string().optional(),
  plan: z.string().optional(),
})

export type SoapValues = z.infer<typeof schema>

interface MedicalRecordSoapFormProps {
  tenant: string
  recordId: string
  defaultValues: SoapValues
  onDone: () => void
}

/**
 * Form revisi SOAP. Empat bagiannya boleh kosong — dokter kerap menyimpan
 * draf lalu melengkapinya setelah pasien pulang.
 */
export function MedicalRecordSoapForm({
  tenant,
  recordId,
  defaultValues,
  onDone,
}: MedicalRecordSoapFormProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const form = useForm(schema, { defaultValues })

  const mutation = useMutation({
    mutationFn: (values: SoapValues) =>
      apiPatch(`/${tenant}/clinic/medical-records/${recordId}`, values),
    onSuccess: () => {
      toast.success(t("medical_record.updated"))
      qc.invalidateQueries({ queryKey: ["medical-record", tenant, recordId] })
      qc.invalidateQueries({ queryKey: ["medical-records"] })
      onDone()
    },
    onError: (err: ApiError) => {
      applyServerErrors(form, err.errors)
      toast.error(err.message)
    },
  })

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        className="space-y-4"
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <FormTextarea
            control={form.control}
            name="subjective"
            label={t("medical_record.subjective")}
            rows={4}
          />
          <FormTextarea
            control={form.control}
            name="objective"
            label={t("medical_record.objective")}
            rows={4}
          />
          <FormTextarea
            control={form.control}
            name="assessment"
            label={t("medical_record.assessment")}
            rows={4}
          />
          <FormTextarea
            control={form.control}
            name="plan"
            label={t("medical_record.plan")}
            rows={4}
          />
        </div>
        <div className="flex items-center gap-2">
          <FormSubmit loading={mutation.isPending}>
            {t("general.save")}
          </FormSubmit>
          <Button
            type="button"
            variant="ghost"
            disabled={mutation.isPending}
            onClick={onDone}
          >
            {t("general.cancel")}
          </Button>
        </div>
      </form>
    </Form>
  )
}

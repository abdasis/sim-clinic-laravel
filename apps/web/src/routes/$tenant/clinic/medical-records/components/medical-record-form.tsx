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
  anamnesis: z.string().optional(),
  skincare_history: z.string().optional(),
  allergy_history: z.string().optional(),
})

export type MedicalRecordValues = z.infer<typeof schema>

interface MedicalRecordFormProps {
  tenant: string
  recordId: string
  defaultValues: MedicalRecordValues
  onDone: () => void
}

/**
 * Form revisi catatan. Setiap bagian boleh kosong — dokter kerap menyimpan
 * draf lalu melengkapinya setelah pasien pulang.
 */
export function MedicalRecordForm({
  tenant,
  recordId,
  defaultValues,
  onDone,
}: MedicalRecordFormProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const form = useForm(schema, { defaultValues })

  const mutation = useMutation({
    mutationFn: (values: MedicalRecordValues) =>
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
        <div className="grid gap-4">
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
              rows={4}
            />
            <FormTextarea
              control={form.control}
              name="allergy_history"
              label={t("medical_record.allergy_history")}
              rows={4}
            />
          </div>
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

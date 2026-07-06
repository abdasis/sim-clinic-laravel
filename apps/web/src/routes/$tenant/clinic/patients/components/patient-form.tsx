import type { Control } from "react-hook-form"
import { z } from "zod"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export const patientSchema = z.object({
  name: z.string().min(1),
  birth_date: z.string().optional(),
  gender: z.string().min(1),
  phone: z.string().min(1),
  whatsapp: z.string().optional(),
  address: z.string().optional(),
})

export type PatientValues = z.infer<typeof patientSchema>

export const patientDefaults: PatientValues = {
  name: "",
  birth_date: "",
  gender: "female",
  phone: "",
  whatsapp: "",
  address: "",
}

export function PatientFormFields({
  control,
}: {
  control: Control<PatientValues>
}) {
  const { t } = useTrans()
  return (
    <div className="space-y-4">
      <FormInput control={control} name="name" label={t("patient.name")} />
      <FormDatePicker
        control={control}
        name="birth_date"
        label={t("patient.birth_date")}
      />
      <FormSelect
        control={control}
        name="gender"
        label={t("patient.gender")}
        options={[
          { label: t("patient.gender_male"), value: "male" },
          { label: t("patient.gender_female"), value: "female" },
          { label: t("patient.gender_other"), value: "other" },
        ]}
      />
      <FormInput control={control} name="phone" label={t("patient.phone")} />
      <FormInput
        control={control}
        name="whatsapp"
        label={t("patient.whatsapp")}
      />
      <FormTextarea
        control={control}
        name="address"
        label={t("patient.address")}
      />
    </div>
  )
}

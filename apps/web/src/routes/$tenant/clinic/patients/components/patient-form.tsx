import type { Control } from "react-hook-form"
import { z } from "zod"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { FormInput } from "#/components/forms/form-input.tsx"
import { FormSelect } from "#/components/forms/form-select.tsx"
import { FormTextarea } from "#/components/forms/form-textarea.tsx"
import { FormDatePicker } from "#/components/forms/form-date-picker.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

export const patientSchema = z.object({
  name: z.string().min(1),
  birth_date: z.string().optional(),
  // Backend menerima gender kosong; memaksanya di sini hanya menghalangi
  // pendaftaran cepat saat pasien belum sempat ditanya.
  gender: z.string().optional(),
  phone: z.string().min(1),
  whatsapp: z.string().optional(),
  address: z.string().optional(),
  notes: z.string().optional(),
})

export type PatientValues = z.infer<typeof patientSchema>

export const patientDefaults: PatientValues = {
  name: "",
  birth_date: "",
  gender: "",
  phone: "",
  whatsapp: "",
  address: "",
  notes: "",
}

export function PatientFormFields({
  control,
}: {
  control: Control<PatientValues>
}) {
  const { t } = useTrans()
  // Tanggal lahir di masa depan pasti ditolak backend; batasi di pemilihnya
  // supaya tidak ada 422 yang sebenarnya bisa dicegah.
  const today = new Date().toISOString().slice(0, 10)

  return (
    <div className="space-y-4">
      <PatientFormSection
        title={t("patient.section_identity")}
        description={t("patient.section_identity_desc")}
      >
        <FormInput
          control={control}
          name="name"
          label={t("patient.name")}
          required
          className="sm:col-span-2"
        />
        <FormDatePicker
          control={control}
          name="birth_date"
          label={t("patient.birth_date")}
          max={today}
        />
        <FormSelect
          control={control}
          name="gender"
          label={t("patient.gender")}
          placeholder={t("patient.gender_placeholder")}
          options={[
            { label: t("patient.gender_male"), value: "male" },
            { label: t("patient.gender_female"), value: "female" },
            { label: t("patient.gender_other"), value: "other" },
          ]}
        />
      </PatientFormSection>

      <PatientFormSection
        title={t("patient.section_contact")}
        description={t("patient.section_contact_desc")}
      >
        <FormInput
          control={control}
          name="phone"
          label={t("patient.phone")}
          type="tel"
          required
          description={t("patient.phone_hint")}
        />
        <FormInput
          control={control}
          name="whatsapp"
          label={t("patient.whatsapp")}
          type="tel"
          description={t("patient.whatsapp_hint")}
        />
        <FormTextarea
          control={control}
          name="address"
          label={t("patient.address")}
          className="sm:col-span-2"
        />
      </PatientFormSection>

      <PatientFormSection
        title={t("patient.section_notes")}
        description={t("patient.section_notes_desc")}
      >
        <FormTextarea
          control={control}
          name="notes"
          label={t("patient.notes")}
          description={t("patient.notes_hint")}
          className="sm:col-span-2"
        />
      </PatientFormSection>
    </div>
  )
}

/**
 * ponytail: sengaja lokal di berkas ini. Ekstrak ke `components/forms/
 * form-section.tsx` saat ada dua form lain yang membutuhkannya — sebelum itu,
 * satu pemakai belum cukup untuk membenarkan abstraksi bersama.
 */
function PatientFormSection({
  title,
  description,
  children,
}: {
  title: string
  description: string
  children: React.ReactNode
}) {
  return (
    <Card className="border-border/50 shadow-sm">
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="grid gap-4 sm:grid-cols-2">{children}</CardContent>
    </Card>
  )
}

import type { Control } from "react-hook-form"
import { useQuery } from "@tanstack/react-query"
import { useParams } from "@tanstack/react-router"
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
import { FormSwitch } from "#/components/forms/form-switch.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"

export const patientSchema = z.object({
  name: z.string().min(1),
  birth_date: z.string().optional(),
  // Backend menerima gender kosong; memaksanya di sini hanya menghalangi
  // pendaftaran cepat saat pasien belum sempat ditanya.
  gender: z.string().optional(),
  // Satu-satunya nomor pasien: pengingat dan broadcast berjalan di atasnya.
  whatsapp: z.string().min(1),
  address: z.string().optional(),
  notes: z.string().optional(),
  // "" berarti tidak ada pembawa; dikonversi ke null saat submit.
  referred_by: z.string().optional(),
  whatsapp_opt_in: z.boolean().optional(),
})

export type PatientValues = z.infer<typeof patientSchema>

export const patientDefaults: PatientValues = {
  name: "",
  birth_date: "",
  gender: "",
  whatsapp: "",
  address: "",
  notes: "",
  referred_by: "",
  whatsapp_opt_in: true,
}

export function PatientFormFields({
  control,
}: {
  control: Control<PatientValues>
}) {
  const { t } = useTrans()
  const { tenant } = useParams({ strict: false }) as { tenant: string }

  // Pilihan pembawa pasien: staf klinik apa pun perannya — resepsionis pun
  // boleh membawa pasien baru dan berhak atas bonusnya.
  const staff = useQuery({
    queryKey: ["staff", tenant, "options"],
    queryFn: () =>
      apiGet<{ data: { id: number; name: string }[] }>(
        `/${tenant}/clinic/staff`,
        { per_page: 100 },
      ),
    enabled: Boolean(tenant),
  })
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
          name="whatsapp"
          label={t("patient.whatsapp")}
          type="tel"
          required
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
        <FormSelect
          control={control}
          name="referred_by"
          label={t("patient.referred_by")}
          description={t("patient.referred_by_hint")}
          options={[
            { label: "—", value: "" },
            ...(staff.data?.data ?? []).map((member) => ({
              label: member.name,
              value: String(member.id),
            })),
          ]}
        />
        <FormSwitch
          control={control}
          name="whatsapp_opt_in"
          label={t("broadcast.opt_in")}
          description={t("broadcast.opt_in_note")}
        />
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

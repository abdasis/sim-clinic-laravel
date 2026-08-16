import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { apiGet } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import { MedicalRecordAttachments } from "../../medical-records/components/medical-record-attachments.tsx"
import type {
  PhotoRow,
  TreatmentRow,
} from "../../medical-records/components/medical-record-attachments.tsx"

export const Route = createFileRoute(
  "/$tenant/clinic/patients/$id/medical-records",
)({
  component: PatientMedicalRecordsPage,
})

interface RecordRow {
  id: number
  created_at?: string | null
  booking?: { id: number; status?: string; start_at?: string | null } | null
  patient_name?: string | null
  author_name?: string | null
  anamnesis?: string | null
  skincare_history?: string | null
  allergy_history?: string | null
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
}

function RecordField({
  label,
  value,
  empty,
}: {
  label: string
  value?: string | null
  empty: string
}) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      {value ? (
        <p className="text-sm leading-relaxed whitespace-pre-wrap">{value}</p>
      ) : (
        <p className="text-sm text-muted-foreground/60 italic">{empty}</p>
      )}
    </div>
  )
}

/**
 * Benar kalau kedua waktu jatuh di tanggal yang berbeda. Dipakai untuk
 * memutuskan perlu tidaknya menyebut waktu tulis: catatan yang dirampungkan
 * di hari yang sama tidak perlu diterangkan, yang menyusul perlu.
 */
function differentDay(visit?: string | null, recorded?: string | null) {
  if (!visit || !recorded) return false

  return new Date(visit).toDateString() !== new Date(recorded).toDateString()
}

function PatientMedicalRecordsPage() {
  const { tenant, id } = useParams({
    from: "/$tenant/clinic/patients/$id/medical-records",
  })
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["patient-medical-records", tenant, id],
    queryFn: () =>
      apiGet<{ data: RecordRow[] }>(
        `/${tenant}/clinic/patients/${id}/medical-records`,
      ),
  })

  const records = data?.data ?? []
  const patientName = records[0]?.patient_name ?? t("patient.title")

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          {
            label: t("patient.title"),
            to: "/$tenant/clinic/patients",
            params: { tenant },
          },
          { label: patientName },
          { label: t("medical_record.title") },
        ]}
      />
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("medical_record.history")}
        </h1>
        <p className="text-sm text-muted-foreground">{patientName}</p>
      </div>

      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-40 w-full" />
        </div>
      ) : records.length === 0 ? (
        <Card className="border-dashed">
          <CardContent className="py-10">
            <EmptyState
              illustration="medical-records"
              title={t("medical_record.empty_patient")}
              description={t("medical_record.empty_patient_desc")}
            />
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {records.map((record) => (
            <Card key={record.id}>
              <CardHeader className="flex-row items-center justify-between gap-3">
                <div className="min-w-0">
                  {/* Yang jadi judul waktu kunjungannya, bukan waktu
                      catatannya ditulis: pada riwayat perkembangan, yang
                      dicari pembaca adalah kapan tindakannya terjadi. */}
                  <CardTitle className="text-base">
                    {formatDateTime(record.booking?.start_at ?? record.created_at)}
                  </CardTitle>
                  <p className="text-xs text-muted-foreground">
                    {record.author_name ? (
                      <span>{record.author_name}</span>
                    ) : null}
                    {/* Waktu tulis hanya disebut kalau memang beda harinya --
                        kalau sama, mengulangnya cuma menambah bising. */}
                    {differentDay(record.booking?.start_at, record.created_at) ? (
                      <span className="text-muted-foreground/70">
                        {record.author_name ? " · " : null}
                        {t("medical_record.recorded_at").replace(
                          ":time",
                          formatDateTime(record.created_at),
                        )}
                      </span>
                    ) : null}
                  </p>
                </div>
                <Button asChild variant="ghost" size="sm">
                  <Link
                    to="/$tenant/clinic/medical-records/$recordId"
                    params={{ tenant, recordId: String(record.id) }}
                  >
                    {t("medical_record.detail")}
                  </Link>
                </Button>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-4">
                  <RecordField
                    label={t("medical_record.anamnesis")}
                    value={record.anamnesis}
                    empty={t("medical_record.section_empty")}
                  />
                  <div className="grid gap-4 sm:grid-cols-2">
                    <RecordField
                      label={t("medical_record.skincare_history")}
                      value={record.skincare_history}
                      empty={t("medical_record.section_empty")}
                    />
                    <RecordField
                      label={t("medical_record.allergy_history")}
                      value={record.allergy_history}
                      empty={t("medical_record.section_empty")}
                    />
                  </div>
                </div>
                <MedicalRecordAttachments
                  treatments={record.treatments}
                  photos={record.photos}
                />
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

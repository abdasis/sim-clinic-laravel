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
  patient_name?: string | null
  author_name?: string | null
  subjective?: string | null
  objective?: string | null
  assessment?: string | null
  plan?: string | null
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
}

function SoapField({
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
          <CardContent className="py-10 text-center">
            <p className="text-sm text-muted-foreground">
              {t("medical_record.empty_patient")}
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {records.map((record) => (
            <Card key={record.id}>
              <CardHeader className="flex-row items-center justify-between gap-3">
                <div>
                  <CardTitle className="text-base">
                    {formatDateTime(record.created_at)}
                  </CardTitle>
                  {record.author_name ? (
                    <p className="text-xs text-muted-foreground">
                      {record.author_name}
                    </p>
                  ) : null}
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
                <div className="grid gap-4 sm:grid-cols-2">
                  <SoapField
                    label={t("medical_record.subjective")}
                    value={record.subjective}
                    empty={t("medical_record.soap_empty")}
                  />
                  <SoapField
                    label={t("medical_record.objective")}
                    value={record.objective}
                    empty={t("medical_record.soap_empty")}
                  />
                  <SoapField
                    label={t("medical_record.assessment")}
                    value={record.assessment}
                    empty={t("medical_record.soap_empty")}
                  />
                  <SoapField
                    label={t("medical_record.plan")}
                    value={record.plan}
                    empty={t("medical_record.soap_empty")}
                  />
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

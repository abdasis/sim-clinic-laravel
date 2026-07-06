import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Card, CardContent, CardHeader, CardTitle } from "#/components/ui/card.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"

export const Route = createFileRoute("/$tenant/clinic/patients/$id/treatments")({
  component: PatientTreatmentsPage,
})

interface PhotoRow {
  id: number
  type: "before" | "after"
  url?: string
  path?: string
}
interface TreatmentRow {
  id: number
  service_name?: string
  notes?: string
}
interface RecordRow {
  id: number
  created_at?: string
  booking_id?: number
  subjective?: string
  objective?: string
  assessment?: string
  plan?: string
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
}

function SoapField({ label, value }: { label: string; value?: string }) {
  if (!value) return null
  return (
    <div>
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="whitespace-pre-wrap text-sm">{value}</p>
    </div>
  )
}

function PatientTreatmentsPage() {
  const { tenant, id } = useParams({ from: "/$tenant/clinic/patients/$id/treatments" })
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["patient-treatments", tenant, id],
    queryFn: () =>
      apiGet<{ data: RecordRow[] }>(`/${tenant}/clinic/patients/${id}/treatments`),
  })

  const records = data?.data ?? []

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic/services", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("patient.title"), to: "/$tenant/clinic/patients", params: { tenant } },
          { label: t("medical_record.treatments") },
        ]}
      />
      <h1 className="mb-4 text-xl font-semibold">{t("medical_record.treatments")}</h1>

      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-40 w-full" />
        </div>
      ) : records.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
      ) : (
        <div className="space-y-4">
          {records.map((record) => (
            <Card key={record.id}>
              <CardHeader>
                <CardTitle className="flex items-center justify-between text-base">
                  <span>{t("medical_record.title")}</span>
                  {record.created_at ? (
                    <span className="text-xs font-normal text-muted-foreground">
                      {new Date(record.created_at).toLocaleString("id-ID")}
                    </span>
                  ) : null}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2">
                  <SoapField label={t("medical_record.subjective")} value={record.subjective} />
                  <SoapField label={t("medical_record.objective")} value={record.objective} />
                  <SoapField label={t("medical_record.assessment")} value={record.assessment} />
                  <SoapField label={t("medical_record.plan")} value={record.plan} />
                </div>

                {record.treatments && record.treatments.length > 0 ? (
                  <div>
                    <p className="mb-2 text-xs font-medium text-muted-foreground">
                      {t("medical_record.treatments")}
                    </p>
                    <ul className="space-y-1 text-sm">
                      {record.treatments.map((tr) => (
                        <li key={tr.id} className="rounded-md border px-3 py-2">
                          <span className="font-medium">{tr.service_name ?? "-"}</span>
                          {tr.notes ? (
                            <span className="text-muted-foreground"> — {tr.notes}</span>
                          ) : null}
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : null}

                {record.photos && record.photos.length > 0 ? (
                  <div>
                    <p className="mb-2 text-xs font-medium text-muted-foreground">
                      {t("medical_record.photos")}
                    </p>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                      {record.photos.map((photo) => (
                        <div key={photo.id} className="space-y-1">
                          <img
                            src={photo.url ?? photo.path}
                            alt={t(`clinic.medical_photo_type.${photo.type}`)}
                            className="h-28 w-full rounded object-cover"
                          />
                          <Badge variant="secondary">
                            {t(`clinic.medical_photo_type.${photo.type}`)}
                          </Badge>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

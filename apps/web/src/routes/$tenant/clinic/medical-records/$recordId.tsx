import { useState } from "react"
import {
  createFileRoute,
  useNavigate,
  useParams,
} from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { Pencil, Trash2 } from "lucide-react"
import { toast } from "sonner"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "#/components/ui/alert-dialog.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "#/components/ui/card.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiGet } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"
import {
  MedicalRecordAttachments,
  type PhotoRow,
  type TreatmentRow,
} from "./components/medical-record-attachments.tsx"
import { MedicalRecordSoapForm } from "./components/medical-record-soap-form.tsx"

export const Route = createFileRoute("/$tenant/clinic/medical-records/$recordId")({
  component: MedicalRecordDetailPage,
})

interface RecordDetail {
  id: number
  patient_id: number
  patient_name?: string | null
  author_name?: string | null
  subjective?: string | null
  objective?: string | null
  assessment?: string | null
  plan?: string | null
  created_at?: string | null
  updated_at?: string | null
  treatments?: TreatmentRow[]
  photos?: PhotoRow[]
}

function SoapSection({
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

function MedicalRecordDetailPage() {
  const { tenant, recordId } = useParams({
    from: "/$tenant/clinic/medical-records/$recordId",
  })
  const { t } = useTrans()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [editing, setEditing] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ["medical-record", tenant, recordId],
    queryFn: () =>
      apiGet<{ data: RecordDetail }>(
        `/${tenant}/clinic/medical-records/${recordId}`,
      ),
  })

  const record = data?.data

  const remove = useMutation({
    mutationFn: () =>
      apiDelete(`/${tenant}/clinic/medical-records/${recordId}`),
    onSuccess: () => {
      toast.success(t("medical_record.deleted"))
      qc.invalidateQueries({ queryKey: ["medical-records"] })
      setDeleteOpen(false)
      navigate({ to: "/$tenant/clinic/medical-records", params: { tenant } })
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setDeleteOpen(false)
    },
  })

  const heading = record?.patient_name ?? t("medical_record.detail")

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          {
            label: t("medical_record.title"),
            to: "/$tenant/clinic/medical-records",
            params: { tenant },
          },
          { label: heading },
        ]}
      />

      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : !record ? (
        <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
      ) : (
        <div className="space-y-4">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h1 className="text-xl font-semibold tracking-tight">{heading}</h1>
              <p className="text-sm text-muted-foreground tabular-nums">
                {formatDateTime(record.created_at)}
                {record.author_name ? ` · ${record.author_name}` : ""}
              </p>
            </div>
            <TooltipProvider>
              <div className="flex items-center gap-2">
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={editing}
                      onClick={() => setEditing(true)}
                    >
                      <Pencil className="size-4" />
                      {t("general.edit")}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent className="flex items-center gap-2">
                    {t("medical_record.edit")}
                    <Kbd>e</Kbd>
                  </TooltipContent>
                </Tooltip>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-destructive hover:text-destructive"
                      onClick={() => setDeleteOpen(true)}
                    >
                      <Trash2 className="size-4" />
                      {t("general.delete")}
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{t("medical_record.delete")}</TooltipContent>
                </Tooltip>
              </div>
            </TooltipProvider>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {editing ? t("medical_record.edit") : t("medical_record.title")}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {editing ? (
                <MedicalRecordSoapForm
                  tenant={tenant}
                  recordId={recordId}
                  defaultValues={{
                    subjective: record.subjective ?? "",
                    objective: record.objective ?? "",
                    assessment: record.assessment ?? "",
                    plan: record.plan ?? "",
                  }}
                  onDone={() => setEditing(false)}
                />
              ) : (
                <div className="grid gap-5 sm:grid-cols-2">
                  <SoapSection
                    label={t("medical_record.subjective")}
                    value={record.subjective}
                    empty={t("medical_record.soap_empty")}
                  />
                  <SoapSection
                    label={t("medical_record.objective")}
                    value={record.objective}
                    empty={t("medical_record.soap_empty")}
                  />
                  <SoapSection
                    label={t("medical_record.assessment")}
                    value={record.assessment}
                    empty={t("medical_record.soap_empty")}
                  />
                  <SoapSection
                    label={t("medical_record.plan")}
                    value={record.plan}
                    empty={t("medical_record.soap_empty")}
                  />
                </div>
              )}
            </CardContent>
          </Card>

          <MedicalRecordAttachments
            treatments={record.treatments}
            photos={record.photos}
          />
        </div>
      )}

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("medical_record.delete")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("medical_record.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={remove.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={remove.isPending}
              onClick={(event) => {
                event.preventDefault()
                remove.mutate()
              }}
            >
              {remove.isPending ? t("general.loading") : t("general.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

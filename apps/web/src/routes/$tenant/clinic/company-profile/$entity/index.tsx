import { useState } from "react"
import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
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
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "#/components/ui/table.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { pickTranslatable } from "#/lib/company-locale.ts"
import { ContentRowActions } from "../components/content-row-actions.tsx"
import { getEntitySchema } from "../components/entity-schema.ts"

export const Route = createFileRoute("/$tenant/clinic/company-profile/$entity/")(
  { component: ContentListPage },
)

type Row = Record<string, unknown> & { id: number; is_active?: boolean }

function ContentListPage() {
  const { tenant, entity } = useParams({
    from: "/$tenant/clinic/company-profile/$entity/",
  })
  const { t } = useTrans()
  const qc = useQueryClient()
  const [pendingDelete, setPendingDelete] = useState<Row | null>(null)

  const schema = getEntitySchema(entity)
  const base = `/${tenant}/clinic/company-profile/${entity}`
  const queryKey = ["company-content", tenant, entity]

  const { data, isLoading } = useQuery({
    queryKey,
    queryFn: () => apiGet<{ data: Row[] }>(base, { per_page: 100 }),
    select: (res) => res.data,
    enabled: Boolean(schema),
  })

  const invalidate = () => qc.invalidateQueries({ queryKey })
  const onError = (err: ApiError) => toast.error(err.message)

  const toggle = useMutation({
    mutationFn: (id: number) => apiPost(`${base}/${id}/toggle`),
    onSuccess: invalidate,
    onError,
  })

  const remove = useMutation({
    mutationFn: (id: number) => apiDelete(`${base}/${id}`),
    onSuccess: () => {
      toast.success(t("company_profile.deleted"))
      setPendingDelete(null)
      invalidate()
    },
    onError: (err: ApiError) => {
      setPendingDelete(null)
      onError(err)
    },
  })

  const reorder = useMutation({
    mutationFn: (ids: number[]) => apiPost(`${base}/reorder`, { ids }),
    onSuccess: invalidate,
    onError,
  })

  if (!schema) {
    return <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
  }

  const rows = data ?? []
  const columns = schema.fields.filter((field) => field.column)

  const move = (index: number, delta: number) => {
    const next = [...rows]
    const target = index + delta

    if (target < 0 || target >= next.length) return

    ;[next[index], next[target]] = [next[target], next[index]]

    reorder.mutate(next.map((row) => row.id))
  }

  return (
    <div>
      <ClinicBreadcrumb
        items={[
          { label: t("clinic.clinic"), to: "/$tenant/clinic", params: { tenant } },
          {
            label: t("company_profile.title"),
            to: "/$tenant/clinic/company-profile",
            params: { tenant },
          },
          { label: t(schema.titleKey) },
        ]}
      />

      <div className="mb-4 flex items-center justify-between gap-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t(schema.titleKey)}
        </h1>
        <Button asChild>
          <Link
            to="/$tenant/clinic/company-profile/$entity/new"
            params={{ tenant, entity }}
          >
            {t("general.add")}
          </Link>
        </Button>
      </div>

      {isLoading ? (
        <Skeleton className="h-56 w-full" />
      ) : rows.length === 0 ? (
        <div className="rounded-lg border border-dashed border-border/50 py-12 text-center">
          <p className="text-sm text-muted-foreground">
            {t("company_profile.empty_section")}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border/50">
          <Table>
            <TableHeader>
              <TableRow>
                {columns.map((column) => (
                  <TableHead key={column.name}>{t(column.labelKey)}</TableHead>
                ))}
                <TableHead>{t("company_profile.is_active")}</TableHead>
                <TableHead className="text-right">
                  {t("general.actions")}
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((row, index) => (
                <TableRow key={row.id}>
                  {columns.map((column) => (
                    <TableCell key={column.name}>
                      {renderCell(row[column.name])}
                    </TableCell>
                  ))}
                  <TableCell>
                    <Badge variant={row.is_active ? "default" : "secondary"}>
                      {row.is_active
                        ? t("company_profile.is_active")
                        : t("general.no")}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <ContentRowActions
                      tenant={tenant}
                      entity={entity}
                      id={row.id}
                      isActive={Boolean(row.is_active)}
                      isFirst={index === 0}
                      isLast={index === rows.length - 1}
                      busy={reorder.isPending || toggle.isPending}
                      onMove={(delta) => move(index, delta)}
                      onToggle={() => toggle.mutate(row.id)}
                      onDelete={() => setPendingDelete(row)}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <AlertDialog
        open={pendingDelete !== null}
        onOpenChange={(open) => !open && setPendingDelete(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("general.delete")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("company_profile.deleted")}
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

                if (pendingDelete) remove.mutate(pendingDelete.id)
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

/** Nilai kolom bisa berupa peta bahasa; tampilkan versi Indonesia. */
function renderCell(value: unknown): string {
  if (value === null || value === undefined) return "-"

  if (typeof value === "object") {
    return String(pickTranslatable(value as Record<string, string>, "id") ?? "-")
  }

  return String(value)
}

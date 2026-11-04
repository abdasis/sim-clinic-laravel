import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"
import { HugeiconsIcon } from "@hugeicons/react"
import { Alert02Icon, ArrowLeft01Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { BroadcastEditForm } from "./components/broadcast-edit-form.tsx"
import type { BroadcastDetail } from "./components/broadcast-types.ts"

export const Route = createFileRoute("/$tenant/clinic/broadcasts/$id_/edit")({
  component: EditBroadcastPage,
})

function EditBroadcastPage() {
  const { tenant, id } = useParams({
    from: "/$tenant/clinic/broadcasts/$id_/edit",
  })
  const { t } = useTrans()

  const { data, isLoading } = useQuery({
    queryKey: ["broadcasts", tenant, id],
    queryFn: () =>
      apiGet<{ data: BroadcastDetail; meta: { message?: string } }>(
        `/${tenant}/clinic/broadcasts/${id}`,
      ),
  })
  const broadcast = data?.data
  const canEdit = broadcast?.can_edit === true

  useBreadcrumbTail(t("broadcast.edit_title"))

  if (isLoading || !broadcast) {
    return (
      <div className="mx-auto max-w-5xl space-y-3">
        <div className="mb-2 space-y-2">
          <div className="h-4 w-24 rounded bg-muted" />
          <div className="h-7 w-56 rounded bg-muted" />
        </div>
        <div className="h-40 w-full rounded-lg border border-border bg-muted/20" />
        <div className="h-56 w-full rounded-lg border border-border bg-muted/20" />
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-5">
        <Link
          to="/$tenant/clinic/broadcasts/$id"
          params={{ tenant, id }}
          className="inline-flex w-fit items-center gap-1 rounded-sm text-xs text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          <HugeiconsIcon
            icon={ArrowLeft01Icon}
            strokeWidth={2}
            className="size-3.5"
          />
          {t("general.back")}
        </Link>
        <h1 className="mt-2 text-xl font-semibold tracking-tight">
          {t("broadcast.edit_title")}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground text-pretty">
          {broadcast.title}
        </p>
      </div>

      {canEdit ? (
        <BroadcastEditForm tenant={tenant} broadcast={broadcast} />
      ) : (
        <div className="rounded-lg border border-border bg-card p-4 sm:p-5">
          <div className="flex items-start gap-2">
            <HugeiconsIcon
              icon={Alert02Icon}
              strokeWidth={2}
              className="mt-0.5 size-4 shrink-0 text-muted-foreground"
            />
            <p className="text-sm text-muted-foreground text-pretty">
              {t("broadcast.edit_locked")}
            </p>
          </div>
          <Button variant="outline" className="mt-4" asChild>
            <Link to="/$tenant/clinic/broadcasts/$id" params={{ tenant, id }}>
              {t("general.back")}
            </Link>
          </Button>
        </div>
      )}
    </div>
  )
}

import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"

import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { NotFoundState } from "#/components/ui/not-found-state.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import { ContentForm } from "../components/content-form.tsx"
import { emptyValues, getEntitySchema } from "../components/entity-schema.ts"

export const Route = createFileRoute(
  "/$tenant/clinic/company-profile/$entity/$id",
)({
  component: EditContentPage,
})

function EditContentPage() {
  const { tenant, entity, id } = useParams({
    from: "/$tenant/clinic/company-profile/$entity/$id",
  })
  const { t } = useTrans()
  const schema = getEntitySchema(entity)

  const { data, isLoading } = useQuery({
    queryKey: ["company-content", tenant, entity, id],
    queryFn: () =>
      apiGet<{ data: Record<string, unknown> }>(
        `/${tenant}/clinic/company-profile/${entity}/${id}`,
      ),
    select: (res) => res.data,
    enabled: Boolean(schema),
  })

  if (!schema) {
    return <NotFoundState
        illustration="company-profile"
        description={t("company_profile.unknown_entity")}
      />
  }

  useBreadcrumbTail(`${t("general.edit")} — ${t(schema.titleKey)}`)
  return (
    <div>
      <h1 className="mb-4 text-xl font-semibold tracking-tight">
        {t("general.edit")} — {t(schema.titleKey)}
      </h1>

      {isLoading || !data ? (
        <Skeleton className="h-72 w-full" />
      ) : (
        <ContentForm
          tenant={tenant}
          schema={schema}
          recordId={Number(id)}
          // Resource mengirim field turunan (image_url, badge_label); form
          // hanya mengambil yang memang bisa diubah.
          initialValues={{ ...emptyValues(schema), ...pickFields(data, schema.fields.map((f) => f.name)) }}
        />
      )}
    </div>
  )
}

function pickFields(
  source: Record<string, unknown>,
  names: string[],
): Record<string, unknown> {
  const picked: Record<string, unknown> = {}

  for (const name of names) {
    if (source[name] !== null && source[name] !== undefined) {
      picked[name] = source[name]
    }
  }

  return picked
}

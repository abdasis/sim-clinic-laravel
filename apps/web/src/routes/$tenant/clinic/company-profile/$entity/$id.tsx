import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"

import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
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
    return <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
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
          {
            label: t(schema.titleKey),
            to: "/$tenant/clinic/company-profile/$entity",
            params: { tenant, entity },
          },
          { label: t("general.edit") },
        ]}
      />
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

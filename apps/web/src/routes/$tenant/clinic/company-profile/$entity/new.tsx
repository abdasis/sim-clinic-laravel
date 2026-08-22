import { createFileRoute, useParams } from "@tanstack/react-router"

import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { NotFoundState } from "#/components/ui/not-found-state.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { ContentForm } from "../components/content-form.tsx"
import { emptyValues, getEntitySchema } from "../components/entity-schema.ts"

export const Route = createFileRoute(
  "/$tenant/clinic/company-profile/$entity/new",
)({
  component: NewContentPage,
})

function NewContentPage() {
  const { tenant, entity } = useParams({
    from: "/$tenant/clinic/company-profile/$entity/new",
  })
  const { t } = useTrans()
  const schema = getEntitySchema(entity)

  if (!schema) {
    return <NotFoundState
        illustration="company-profile"
        description={t("company_profile.unknown_entity")}
      />
  }

  useBreadcrumbTail(`${t("general.create")} — ${t(schema.titleKey)}`)
  return (
    <div>
      <h1 className="mb-4 text-xl font-semibold tracking-tight">
        {t("general.create")} — {t(schema.titleKey)}
      </h1>

      <ContentForm
        tenant={tenant}
        schema={schema}
        initialValues={emptyValues(schema)}
      />
    </div>
  )
}

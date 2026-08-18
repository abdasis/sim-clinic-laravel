import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { ExternalLink } from "lucide-react"
import { toast } from "sonner"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Card, CardContent } from "#/components/ui/card.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  ENTITY_ORDER,
  ENTITY_SCHEMAS,
} from "./components/entity-schema.ts"

export const Route = createFileRoute("/$tenant/clinic/company-profile/")({
  component: CompanyProfileOverviewPage,
})

interface Settings {
  is_published: boolean
}

function CompanyProfileOverviewPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/company-profile/" })
  const { t } = useTrans()
  const qc = useQueryClient()

  const { data } = useQuery({
    queryKey: ["company-settings", tenant],
    queryFn: () =>
      apiGet<{ data: Settings | null }>(
        `/${tenant}/clinic/company-profile/settings`,
      ),
    select: (res) => res.data,
  })

  const publish = useMutation({
    mutationFn: () =>
      apiPost<{ meta: { message: string } }>(
        `/${tenant}/clinic/company-profile/settings/publish`,
      ),
    onSuccess: (res) => {
      toast.success(res.meta.message)
      qc.invalidateQueries({ queryKey: ["company-settings", tenant] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const published = data?.is_published ?? false

  return (
    <div>

      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">
            {t("company_profile.title")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {t("company_profile.subtitle")}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant={published ? "default" : "secondary"}>
            {published
              ? t("company_profile.published")
              : t("company_profile.unpublished")}
          </Badge>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                disabled={publish.isPending}
                onClick={() => publish.mutate()}
              >
                {published
                  ? t("company_profile.unpublish")
                  : t("company_profile.publish")}
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              {published
                ? t("company_profile.unpublish")
                : t("company_profile.publish")}
            </TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button asChild variant="ghost" size="icon">
                <a href={`/${tenant}`} target="_blank" rel="noreferrer noopener">
                  <ExternalLink className="size-4" />
                </a>
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("company_profile.title")}</TooltipContent>
          </Tooltip>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Card className="transition-colors hover:border-border">
          <CardContent className="p-4">
            <Link
              to="/$tenant/clinic/company-profile/settings"
              params={{ tenant }}
              className="block"
            >
              <p className="text-sm font-medium">
                {t("company_profile.settings")}
              </p>
              <p className="mt-0.5 text-sm text-muted-foreground">
                {t("company_profile.subtitle")}
              </p>
            </Link>
          </CardContent>
        </Card>

        {ENTITY_ORDER.map((slug) => (
          <Card key={slug} className="transition-colors hover:border-border">
            <CardContent className="p-4">
              <Link
                to="/$tenant/clinic/company-profile/$entity"
                params={{ tenant, entity: slug }}
                className="block"
              >
                <p className="text-sm font-medium">
                  {t(ENTITY_SCHEMAS[slug].titleKey)}
                </p>
                <p className="mt-0.5 text-sm text-muted-foreground">
                  {ENTITY_SCHEMAS[slug].fields.length}{" "}
                  {t("company_profile.label").toLowerCase()}
                </p>
              </Link>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}

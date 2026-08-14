import { createFileRoute, Link, useParams } from "@tanstack/react-router"
import { ArrowLeft } from "lucide-react"

import { CompanyLocaleProvider } from "#/components/company-profile/locale-context.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useCompanyTreatment } from "#/hooks/use-company-profile.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { normalizeLocale, pickTranslatable } from "#/lib/company-locale.ts"

export const Route = createFileRoute("/$tenant/treatment/$slug")({
  component: TreatmentDetailPage,
  validateSearch: (search: Record<string, unknown>) => ({
    lang: search.lang ? String(search.lang) : undefined,
  }),
})

function TreatmentDetailPage() {
  const { tenant, slug } = useParams({ from: "/$tenant/treatment/$slug" })
  const { lang } = Route.useSearch()
  const locale = normalizeLocale(lang)
  const { t } = useTrans(locale)

  const { data, isLoading, isError } = useCompanyTreatment(tenant, slug, locale)

  const title = pickTranslatable(data?.title, locale)
  const description = pickTranslatable(data?.description, locale)

  return (
    <CompanyLocaleProvider locale={locale}>
      <main className="mx-auto w-full max-w-3xl px-4 py-12">
        <Button asChild variant="ghost" size="sm" className="mb-6 -ml-2">
          <Link to="/$tenant" params={{ tenant }} search={{ lang: locale }}>
            <ArrowLeft className="size-4" />
            {t("general.back")}
          </Link>
        </Button>

        {isLoading ? (
          <div className="space-y-4">
            <Skeleton className="h-9 w-72" />
            <Skeleton className="aspect-[16/9] w-full" />
            <Skeleton className="h-32 w-full" />
          </div>
        ) : isError || !data ? (
          <p className="text-sm text-muted-foreground">{t("general.no_data")}</p>
        ) : (
          <article className="space-y-6">
            <header className="space-y-2">
              <div className="flex flex-wrap items-center gap-1.5">
                {data.badge_label ? (
                  <Badge variant="secondary">{data.badge_label}</Badge>
                ) : null}
                {data.category_tags.map((tag) => (
                  <span
                    key={tag}
                    className="rounded-sm bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground"
                  >
                    {tag}
                  </span>
                ))}
              </div>
              <h1 className="text-2xl font-semibold tracking-tight text-balance">
                {title}
              </h1>
              {description ? (
                <p className="text-sm leading-relaxed text-muted-foreground text-pretty">
                  {description}
                </p>
              ) : null}
            </header>

            {data.image_url ? (
              <img
                src={data.image_url}
                alt={title ?? ""}
                className="aspect-[16/9] w-full rounded-lg border border-border/50 object-cover"
              />
            ) : null}
          </article>
        )}
      </main>
    </CompanyLocaleProvider>
  )
}

import { createFileRoute } from "@tanstack/react-router"

import { useContentLocale, useContentText } from "#/components/company-profile/locale-context.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { ARTICLES } from "#/lib/landing-content.ts"
import { formatDate } from "#/lib/format.ts"

export const Route = createFileRoute("/_marketing/artikel/")({
  component: ArticleListPage,
})

function ArticleListPage() {
  const text = useContentText()
  const isEnglish = useContentLocale() === "en"

  return (
    <MarketingPage
      crumbs={[{ label: isEnglish ? "Articles" : "Artikel" }]}
      title={isEnglish ? "Articles" : "Artikel"}
      description={
        isEnglish
          ? "Notes from our doctors on skin, treatments, and what to expect."
          : "Catatan dari dokter kami soal kulit, treatment, dan apa yang bisa diharapkan."
      }
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {ARTICLES.map((article) => (
          <article
            key={article.id}
            className="group flex flex-col overflow-hidden rounded-lg border border-border/50 bg-background transition-all hover:border-border hover:shadow-sm"
          >
            <div className="aspect-[16/10] overflow-hidden bg-muted">
              <img
                src={article.image_url}
                alt={text(article.title) ?? ""}
                loading="lazy"
                className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
              />
            </div>
            <div className="flex flex-1 flex-col gap-2 p-4">
              <div className="flex items-center gap-2">
                <Badge variant="secondary">{text(article.category)}</Badge>
                <span className="text-xs text-muted-foreground tabular-nums">
                  {formatDate(article.published_at)}
                </span>
              </div>
              <h2 className="text-sm font-semibold tracking-tight text-balance">
                {text(article.title)}
              </h2>
              <p className="text-sm leading-relaxed text-muted-foreground">
                {text(article.excerpt)}
              </p>
            </div>
          </article>
        ))}
      </div>
    </MarketingPage>
  )
}

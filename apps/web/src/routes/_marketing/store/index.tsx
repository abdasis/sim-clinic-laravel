import { createFileRoute, Link } from "@tanstack/react-router"

import { useContentLocale, useContentText } from "#/components/company-profile/locale-context.tsx"
import { MarketingPage } from "#/components/marketing/marketing-breadcrumb.tsx"
import { Button } from "#/components/ui/button.tsx"
import { formatCurrency } from "#/lib/format.ts"
import { STORE_PRODUCTS } from "#/lib/landing-content.ts"

export const Route = createFileRoute("/_marketing/store/")({
  component: StorePage,
})

function StorePage() {
  const locale = useContentLocale()
  const text = useContentText()
  const isEnglish = locale === "en"

  return (
    <MarketingPage
      crumbs={[{ label: "E-Store" }]}
      title={isEnglish ? "Clinic e-store" : "E-Store klinik"}
      description={
        isEnglish
          ? "The same products we use in the clinic. Prices start from Rp85.000."
          : "Produk yang sama dengan yang kami pakai di klinik. Harga mulai Rp85.000."
      }
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {STORE_PRODUCTS.map((product) => (
          <article
            key={product.id}
            className="group flex flex-col overflow-hidden rounded-lg border border-border/50 bg-background transition-all hover:border-border hover:shadow-sm"
          >
            <div className="aspect-square overflow-hidden bg-muted">
              <img
                src={product.image_url}
                alt={text(product.name) ?? ""}
                loading="lazy"
                className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
              />
            </div>
            <div className="flex flex-1 flex-col gap-1.5 p-4">
              <h2 className="text-sm font-semibold tracking-tight">
                {text(product.name)}
              </h2>
              <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">
                {text(product.description)}
              </p>
              <div className="mt-auto flex items-center justify-between gap-2 pt-3">
                <span className="text-sm font-medium tabular-nums">
                  {formatCurrency(product.price)}
                </span>
                <Button asChild size="sm" variant="outline">
                  <Link to="/cart" search={{ lang: locale }}>
                    {isEnglish ? "Add" : "Tambah"}
                  </Link>
                </Button>
              </div>
            </div>
          </article>
        ))}
      </div>
    </MarketingPage>
  )
}

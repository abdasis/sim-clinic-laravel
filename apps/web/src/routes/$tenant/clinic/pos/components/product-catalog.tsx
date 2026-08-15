import { ScrollArea } from "#/components/ui/scroll-area.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { CatalogEntry } from "../hooks/use-pos-catalog.ts"
import { usePosCatalog } from "../hooks/use-pos-catalog.ts"
import { PosFilters } from "./pos-filters.tsx"
import { ProductCard } from "./product-card.tsx"

interface ProductCatalogProps {
  tenant: string
  onAdd: (entry: CatalogEntry) => void
  searchRef?: React.Ref<HTMLInputElement>
}

const GRID = "grid grid-cols-2 gap-2.5 p-3 sm:grid-cols-3 xl:grid-cols-4"

export function ProductCatalog({ tenant, onAdd, searchRef }: ProductCatalogProps) {
  const { t } = useTrans()
  const { visible, filters, setFilters, resetFilters, isLoading, isError } =
    usePosCatalog(tenant)

  return (
    <section className="flex min-h-0 flex-1 flex-col overflow-hidden border-r border-border/50">
      <PosFilters
        filters={filters}
        onChange={setFilters}
        searchRef={searchRef}
      />

      <ScrollArea className="min-h-0 flex-1">
        {isLoading ? (
          <div className={GRID}>
            {Array.from({ length: 8 }, (_, index) => (
              <Skeleton key={index} className="h-[118px] w-full rounded-lg" />
            ))}
          </div>
        ) : isError ? (
          <EmptyState
            className="py-16"
            illustration="products"
            title={t("stats.error_title")}
            description={t("stats.error_description")}
          />
        ) : visible.length === 0 ? (
          <EmptyState
            className="py-16"
            illustration="products"
            title={t("pos.catalog.empty_title")}
            description={t("pos.catalog.empty_desc")}
            action={
              <Button variant="outline" size="sm" onClick={resetFilters}>
                {t("pos.filter.stock_all")}
              </Button>
            }
          />
        ) : (
          <div className={GRID}>
            {visible.map((entry) => (
              <ProductCard
                key={`${entry.kind}:${entry.id}`}
                entry={entry}
                onAdd={onAdd}
              />
            ))}
          </div>
        )}
      </ScrollArea>
    </section>
  )
}

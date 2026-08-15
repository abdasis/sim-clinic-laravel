import { HugeiconsIcon } from "@hugeicons/react"
import { Search01Icon } from "@hugeicons/core-free-icons"

import { Input } from "#/components/ui/input.tsx"
import {
  NativeSelect,
  NativeSelectOption,
} from "#/components/ui/native-select.tsx"
import { Tabs, TabsList, TabsTrigger } from "#/components/ui/tabs.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type {
  CatalogFilters,
  SortKey,
  StockFilter,
  TypeFilter,
} from "../hooks/use-pos-catalog.ts"

interface PosFiltersProps {
  filters: CatalogFilters
  onChange: (filters: CatalogFilters) => void
  searchRef?: React.Ref<HTMLInputElement>
}

export function PosFilters({ filters, onChange, searchRef }: PosFiltersProps) {
  const { t } = useTrans()
  const patch = (next: Partial<CatalogFilters>) =>
    onChange({ ...filters, ...next })

  // Layanan tidak punya stok, jadi menyaringnya tidak bermakna — kontrolnya
  // dimatikan dan nilainya dikembalikan supaya tidak ada filter tersembunyi.
  const stockDisabled = filters.type === "service"

  return (
    <div className="flex flex-col gap-2.5 border-b border-border/50 p-3">
      <Tooltip>
        <TooltipTrigger asChild>
          <div className="relative">
            <HugeiconsIcon
              icon={Search01Icon}
              strokeWidth={2}
              className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              ref={searchRef}
              value={filters.search}
              onChange={(event) => patch({ search: event.target.value })}
              placeholder={t("pos.filter.search")}
              aria-label={t("pos.filter.search")}
              className="h-9 pl-8"
            />
          </div>
        </TooltipTrigger>
        <TooltipContent className="flex items-center gap-2">
          {t("pos.shortcut.search")}
          <Kbd>/</Kbd>
        </TooltipContent>
      </Tooltip>

      <div className="flex flex-wrap items-center gap-2">
        <Tabs
          value={filters.type}
          onValueChange={(value) =>
            patch({
              type: value as TypeFilter,
              stock: value === "service" ? "all" : filters.stock,
            })
          }
        >
          <TabsList className="h-8">
            <TabsTrigger value="all" className="text-xs">
              {t("pos.filter.all")}
            </TabsTrigger>
            <TabsTrigger value="service" className="text-xs">
              {t("pos.filter.services")}
            </TabsTrigger>
            <TabsTrigger value="product" className="text-xs">
              {t("pos.filter.products")}
            </TabsTrigger>
          </TabsList>
        </Tabs>

        <Tooltip>
          <TooltipTrigger asChild>
            <NativeSelect
              value={filters.stock}
              disabled={stockDisabled}
              aria-label={t("pos.filter.stock")}
              onChange={(event) =>
                patch({ stock: event.target.value as StockFilter })
              }
              className="h-8 w-auto text-xs"
            >
              <NativeSelectOption value="all">
                {t("pos.filter.stock_all")}
              </NativeSelectOption>
              <NativeSelectOption value="available">
                {t("pos.stock.available")}
              </NativeSelectOption>
              <NativeSelectOption value="low">
                {t("pos.stock.low")}
              </NativeSelectOption>
              <NativeSelectOption value="out">
                {t("pos.stock.out")}
              </NativeSelectOption>
            </NativeSelect>
          </TooltipTrigger>
          <TooltipContent>
            {stockDisabled ? t("pos.filter.stock_disabled") : t("pos.filter.stock")}
          </TooltipContent>
        </Tooltip>

        <NativeSelect
          value={filters.sort}
          aria-label={t("pos.filter.sort")}
          onChange={(event) => patch({ sort: event.target.value as SortKey })}
          className="ml-auto h-8 w-auto text-xs"
        >
          <NativeSelectOption value="name">
            {t("pos.filter.sort_name")}
          </NativeSelectOption>
          <NativeSelectOption value="price_asc">
            {t("pos.filter.sort_price_asc")}
          </NativeSelectOption>
          <NativeSelectOption value="price_desc">
            {t("pos.filter.sort_price_desc")}
          </NativeSelectOption>
        </NativeSelect>
      </div>
    </div>
  )
}

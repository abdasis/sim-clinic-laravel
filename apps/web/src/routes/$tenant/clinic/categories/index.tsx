import { createFileRoute, useParams } from "@tanstack/react-router"
import { useEffect, useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"

import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Tabs, TabsList, TabsTrigger } from "#/components/ui/tabs.tsx"
import {
  ARCHIVABLE_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { CategoryRow, CategoryType } from "#/hooks/use-category-options.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { CategoryActionsCell } from "./components/category-actions-cell.tsx"
import { CategoryFormDialog } from "./components/category-form-dialog.tsx"

export const Route = createFileRoute("/$tenant/clinic/categories/")({
  component: CategoriesPage,
})

const TYPES: CategoryType[] = ["service", "product", "expense"]

function CategoriesPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/categories/" })
  const { t } = useTrans()
  const [createOpen, setCreateOpen] = useState(false)
  const [type, setType] = useState<CategoryType>("service")

  // Angka 1-3 memindah jenis tanpa meraih tetikus; aman dari pintasan bawaan
  // browser karena tanpa modifier.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null

      if (
        event.metaKey ||
        event.ctrlKey ||
        event.altKey ||
        target?.tagName === "INPUT" ||
        target?.tagName === "TEXTAREA" ||
        target?.isContentEditable
      ) {
        return
      }

      const index = Number(event.key) - 1

      if (index >= 0 && index < TYPES.length) {
        setType(TYPES[index])
      }
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [])

  const columns = useMemo<ColumnDef<CategoryRow>[]>(
    () => [
      { accessorKey: "name", header: t("category.name") },
      {
        accessorKey: "description",
        header: t("category.description"),
        cell: ({ row }) =>
          row.original.description ? (
            <span className="line-clamp-1 text-muted-foreground">
              {row.original.description}
            </span>
          ) : (
            <span className="text-muted-foreground">-</span>
          ),
      },
      {
        accessorKey: "usage_count",
        header: t("category.usage_count"),
        cell: ({ row }) => {
          const count = row.original.usage_count ?? 0

          return count > 0 ? (
            <Badge variant="secondary" className="font-normal tabular-nums">
              {count}
            </Badge>
          ) : (
            <span className="text-xs text-muted-foreground">
              {t("category.unused")}
            </span>
          )
        },
      },
      {
        accessorKey: "status",
        header: t("category.status"),
        cell: ({ row }) => (
          <StatusBadge
            status={row.original.status}
            label={row.original.status_label}
            variantMap={ARCHIVABLE_STATUS_VARIANTS}
          />
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end">
            <CategoryActionsCell
              tenant={tenant}
              category={row.original}
              usageCount={row.original.usage_count ?? 0}
            />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } =
    useDataTable<CategoryRow>({
      // Jenis masuk ke kunci kueri: berpindah tab harus memuat daftar lain,
      // bukan menyaring daftar yang sudah termuat.
      queryKey: ["categories", tenant, type],
      queryFn: (params: DataTableParams) =>
        apiGet<DataTableResponse<CategoryRow>>(`/${tenant}/clinic/categories`, {
          page: params.page,
          per_page: params.per_page,
          sort: params.sort,
          direction: params.direction,
          search: params.search,
          filter: { ...params.filters, type, status: "all" },
        }),
      columns,
    })

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold tracking-tight">
            {t("category.title")}
          </h1>
          <p className="mt-1 text-sm text-pretty text-muted-foreground">
            {t("category.empty_desc")}
          </p>
        </div>
        <Button onClick={() => setCreateOpen(true)}>{t("category.add")}</Button>
      </div>

      <Tabs
        value={type}
        onValueChange={(value) => setType(value as CategoryType)}
        className="mb-3"
      >
        <TabsList>
          {TYPES.map((value, index) => (
            <Tooltip key={value}>
              <TooltipTrigger asChild>
                <TabsTrigger value={value}>
                  {t(`category.type_${value}`)}
                </TabsTrigger>
              </TooltipTrigger>
              <TooltipContent className="flex items-center gap-2">
                {t(`category.type_${value}`)}
                <Kbd>{index + 1}</Kbd>
              </TooltipContent>
            </Tooltip>
          ))}
        </TabsList>
      </Tabs>

      <CategoryFormDialog
        tenant={tenant}
        defaultType={type}
        open={createOpen}
        onOpenChange={setCreateOpen}
      />

      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        emptyIllustration="default"
        emptyTitle={t("category.empty_title")}
        emptyDescription={t("category.empty_desc")}
      />
    </div>
  )
}

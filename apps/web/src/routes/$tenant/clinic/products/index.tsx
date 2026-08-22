import { createFileRoute, useNavigate, useParams } from "@tanstack/react-router"
import { PackageIcon } from "@hugeicons/core-free-icons"
import { useMemo, useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import {
  ARCHIVABLE_STATUS_VARIANTS,
  StatusBadge,
} from "#/components/ui/status-badge.tsx"
import { IndexCta } from "#/components/stats/index-cta.tsx"
import { StatsSection } from "#/components/stats/stats-section.tsx"
import { useStats } from "#/hooks/use-stats.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { Button } from "#/components/ui/button.tsx"
import { CatalogImportDialog } from "#/components/import/catalog-import-dialog.tsx"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"
import { ProductActionsCell } from "./components/product-actions-cell.tsx"

export const Route = createFileRoute("/$tenant/clinic/products/")({
  component: ProductsPage,
})

interface ProductRow {
  id: number
  name: string
  type?: string | null
  type_label?: string | null
  is_sellable?: boolean
  /** Dibawa untuk prefill dialog ubah; tabelnya menampilkan category_label. */
  category_id?: number | null
  category?: string | null
  category_label?: string | null
  unit_id?: number | null
  unit?: string | null
  unit_label?: string | null
  stock_balance: number
  min_threshold: number
  price: string
  status: string
  /** Dibawa untuk prefill editor Informasi Produk di dialog ubah. */
  knowledge?: RichTextDoc | null
  status_label: string
  is_low_stock: boolean
}

function ProductsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/products/" })
  const navigate = useNavigate()
  const goToCreate = () =>
    navigate({ to: "/$tenant/clinic/products/new", params: { tenant } })
  const { t } = useTrans()
  const [importOpen, setImportOpen] = useState(false)
  const stats = useStats({ tenant, module: "products" })

  const columns = useMemo<ColumnDef<ProductRow>[]>(
    () => [
      { accessorKey: "name", header: t("product.name") },
      {
        accessorKey: "type",
        header: t("product.type"),
        cell: ({ row }) => (
          <Badge
            variant={row.original.is_sellable === false ? "outline" : "secondary"}
            className="font-normal"
          >
            {row.original.type_label ?? t("product.type_retail")}
          </Badge>
        ),
      },
      {
        accessorKey: "category",
        header: t("product.category"),
        cell: ({ row }) =>
          row.original.category_label ? (
            <Badge variant="secondary" className="font-normal">
              {row.original.category_label}
            </Badge>
          ) : (
            <span className="text-muted-foreground">-</span>
          ),
      },
      {
        accessorKey: "unit_label",
        header: t("product.unit"),
        cell: ({ row }) => (
          <span className={row.original.unit_label ? "" : "text-muted-foreground"}>
            {row.original.unit_label ?? "-"}
          </span>
        ),
      },
      {
        accessorKey: "stock_balance",
        header: t("product.stock_balance"),
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <span>{row.original.stock_balance}</span>
            {row.original.is_low_stock ? (
              <Badge variant="destructive">{t("product.low_stock")}</Badge>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "status",
        header: t("product.status"),
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
            <ProductActionsCell tenant={tenant} product={row.original} />
          </div>
        ),
      },
    ],
    [t, tenant],
  )

  const { table, isLoading, meta, isError, refetch, error } = useDataTable<ProductRow>({
    queryKey: ["products", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<ProductRow>>(`/${tenant}/clinic/products`, {
        page: params.page,
        per_page: params.per_page,
        sort: params.sort,
        direction: params.direction,
        search: params.search,
        filter: params.filters,
      }),
    columns,
  })

  return (
    <div>
      <IndexCta
        icon={PackageIcon}
        tone="sand"
        mascot="clinic"
        title={t("cta.products.title")}
        description={t("cta.products.description")}
        actionLabel={t("cta.products.action")}
        onAction={goToCreate}
      />

      <StatsSection
        stats={stats.data?.data}
        isLoading={stats.isLoading}
        isError={stats.isError}
        isFetching={stats.isFetching}
        onRefresh={() => void stats.refetch()}
      />

      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold tracking-tight">{t("product.title")}</h1>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => setImportOpen(true)}>
            {t("product.import")}
          </Button>
          <Button onClick={goToCreate}>{t("product.add")}</Button>
        </div>
      </div>
      <CatalogImportDialog
        tenant={tenant}
        resource="products"
        templateName="template-produk.xlsx"
        queryKey="products"
        open={importOpen}
        onOpenChange={setImportOpen}
      />
      <DataTable
        table={table}
        isLoading={isLoading}
        isError={isError}
        error={error}
        onRetry={() => void refetch()}
        searchPlaceholder={t("general.search")}
        meta={meta}
        faceted={[
          {
            columnId: "status",
            title: t("product.status"),
            options: [
              { label: t("product.status_active"), value: "active" },
              { label: t("product.status_archived"), value: "archived" },
            ],
          },
        ]}
        emptyIllustration="products"
        emptyTitle={t("product.empty_title")}
        emptyDescription={t("product.empty_desc")}
      />
    </div>
  )
}

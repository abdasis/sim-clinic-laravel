import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { ProductFormModal } from "./components/product-form-modal.tsx"

export const Route = createFileRoute("/$tenant/clinic/products/")({
  component: ProductsPage,
})

interface ProductRow {
  id: number
  name: string
  unit: string
  stock_balance: number
  status: string
  status_label: string
  is_low_stock: boolean
}

function ProductsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/products/" })
  const { t } = useTrans()

  const columns = useMemo<ColumnDef<ProductRow>[]>(
    () => [
      { accessorKey: "name", header: t("product.name") },
      { accessorKey: "unit", header: t("product.unit") },
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
        cell: ({ row }) => <Badge>{row.original.status_label}</Badge>,
      },
    ],
    [t],
  )

  const { table, isLoading, meta } = useDataTable<ProductRow>({
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
      <ClinicBreadcrumb
        items={[
          { label: tenant, to: "/$tenant/clinic", params: { tenant } },
          { label: t("clinic.clinic") },
          { label: t("product.title") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("product.title")}</h1>
        <ProductFormModal tenant={tenant} />
      </div>
      <DataTable
        table={table}
        isLoading={isLoading}
        searchPlaceholder={t("general.search")}
        meta={meta}
      />
    </div>
  )
}

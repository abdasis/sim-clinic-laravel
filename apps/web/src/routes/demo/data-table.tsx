import { useMemo } from "react"
import { createFileRoute, Link } from "@tanstack/react-router"
import { format } from "date-fns"
import { DataTableColumnHeader } from "#/components/datatable/datatable-column-header.tsx"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { apiGet } from "#/lib/api.ts"
import type {
  DataTableColumnDef,
  DataTableParams,
  DataTableResponse,
  FacetedOption,
} from "#/types/data-table.ts"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "#/components/ui/breadcrumb.tsx"

export const Route = createFileRoute("/demo/data-table")({
  component: DemoDataTableRoute,
})

interface DemoRow {
  id: number
  name: string
  category: string
  amount: number
  created_at: string
}

const CATEGORIES: FacetedOption[] = [
  { label: "Electronics", value: "Electronics" },
  { label: "Books", value: "Books" },
  { label: "Clothing", value: "Clothing" },
  { label: "Food", value: "Food" },
  { label: "Tools", value: "Tools" },
]

function fetchDemoData(
  params: DataTableParams,
): Promise<DataTableResponse<DemoRow>> {
  return apiGet<DataTableResponse<DemoRow>>("/api/demo/data", {
    page: params.page,
    per_page: params.per_page,
    sort: params.sort,
    direction: params.direction,
    search: params.search,
    filters: params.filters,
  })
}

function DemoDataTableRoute() {
  const columns = useMemo<DataTableColumnDef<DemoRow>[]>(
    () => [
      {
        accessorKey: "id",
        header: ({ column }) => (
          <DataTableColumnHeader column={column} title="ID" />
        ),
      },
      {
        accessorKey: "name",
        header: ({ column }) => (
          <DataTableColumnHeader column={column} title="Name" />
        ),
      },
      {
        accessorKey: "category",
        header: ({ column }) => (
          <DataTableColumnHeader column={column} title="Category" />
        ),
      },
      {
        accessorKey: "amount",
        header: ({ column }) => (
          <DataTableColumnHeader column={column} title="Amount" />
        ),
        cell: ({ row }) =>
          new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
          }).format(Number(row.original.amount)),
      },
      {
        accessorKey: "created_at",
        header: ({ column }) => (
          <DataTableColumnHeader column={column} title="Created" />
        ),
        cell: ({ row }) =>
          format(new Date(row.original.created_at), "yyyy-MM-dd"),
      },
    ],
    [],
  )

  const { table, meta, isLoading, isError } = useDataTable<DemoRow>({
    queryKey: ["demo-data"],
    queryFn: fetchDemoData,
    columns,
    initialPageSize: 10,
  })

  return (
    <main className="mx-auto max-w-5xl px-4 py-6">
      <Breadcrumb className="mb-4">
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/">Home</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/demo/tanstack-query">Demo</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>Data Table</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      <h1 className="text-2xl font-semibold mb-4">Data Table (server-side)</h1>

      {isError ? (
        <p className="text-destructive">Failed loading data.</p>
      ) : (
        <DataTable
          table={table}
          isLoading={isLoading}
          meta={meta}
          searchPlaceholder="Search name or category..."
          faceted={[{ columnId: "category", title: "Category", options: CATEGORIES }]}
        />
      )}
    </main>
  )
}
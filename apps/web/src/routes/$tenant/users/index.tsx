import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMemo, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { MoreHorizontal } from "lucide-react"
import { toast } from "sonner"
import type { ColumnDef } from "@tanstack/react-table"
import { useDataTable } from "#/hooks/use-data-table.ts"
import { DataTable } from "#/components/datatable/datatable.tsx"
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "#/components/ui/alert-dialog.tsx"
import { ClinicBreadcrumb } from "#/components/clinic-breadcrumb.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPatch, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { getAuthUser } from "#/lib/auth.ts"
import type { DataTableParams, DataTableResponse } from "#/types/data-table.ts"
import { InviteModal } from "./components/invite-modal.tsx"

export const Route = createFileRoute("/$tenant/users/")({
  component: UsersPage,
})

interface UserRow {
  id: number
  name: string
  email: string
  role: string
  status: string
}

function UserRowActions({ tenant, user }: { tenant: string; user: UserRow }) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [confirmOpen, setConfirmOpen] = useState(false)

  const roleMutation = useMutation({
    mutationFn: (role: string) =>
      apiPatch(`/${tenant}/users/${user.id}/role`, { role }),
    onSuccess: () => {
      toast.success(t("tenant.role_changed"))
      qc.invalidateQueries({ queryKey: ["users"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const removeMutation = useMutation({
    mutationFn: () => apiPost(`/${tenant}/users/${user.id}/remove`),
    onSuccess: () => {
      toast.success(t("tenant.user_removed"))
      qc.invalidateQueries({ queryKey: ["users"] })
      setConfirmOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setConfirmOpen(false)
    },
  })

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon-sm">
            <MoreHorizontal />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuLabel>{t("tenant.user_role")}</DropdownMenuLabel>
          <DropdownMenuItem
            disabled={user.role === "member"}
            onClick={() => roleMutation.mutate("member")}
          >
            {t("tenant.role.member")}
          </DropdownMenuItem>
          <DropdownMenuItem
            disabled={user.role === "tenant_admin"}
            onClick={() => roleMutation.mutate("tenant_admin")}
          >
            {t("tenant.role.tenant_admin")}
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            variant="destructive"
            onClick={() => setConfirmOpen(true)}
          >
            {t("general.delete")}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("general.confirm")}</AlertDialogTitle>
            <AlertDialogDescription>{user.email}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("general.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={(e) => {
                e.preventDefault()
                removeMutation.mutate()
              }}
            >
              {t("general.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

function UsersPage() {
  const { tenant } = useParams({ from: "/$tenant/users/" })
  const { t } = useTrans()
  const isAdmin = getAuthUser()?.role === "tenant_admin"

  const columns = useMemo<ColumnDef<UserRow>[]>(() => {
    const base: ColumnDef<UserRow>[] = [
      { accessorKey: "name", header: t("staff.name") },
      { accessorKey: "email", header: t("tenant.email") },
      {
        accessorKey: "role",
        header: t("tenant.user_role"),
        cell: ({ row }) => (
          <Badge variant="secondary">
            {t(`tenant.role.${row.original.role}`)}
          </Badge>
        ),
      },
      {
        accessorKey: "status",
        header: t("tenant.tenant_status"),
        cell: ({ row }) => (
          <Badge>{t(`tenant.user_status.${row.original.status}`)}</Badge>
        ),
      },
    ]
    if (isAdmin) {
      base.push({
        id: "actions",
        header: t("general.actions"),
        cell: ({ row }) => (
          <UserRowActions tenant={tenant} user={row.original} />
        ),
      })
    }
    return base
  }, [t, isAdmin, tenant])

  const { table, isLoading, meta } = useDataTable<UserRow>({
    queryKey: ["users", tenant],
    queryFn: (params: DataTableParams) =>
      apiGet<DataTableResponse<UserRow>>(`/${tenant}/users`, {
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
          { label: t("tenant.users") },
        ]}
      />
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("tenant.users")}</h1>
        {isAdmin ? <InviteModal tenant={tenant} /> : null}
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

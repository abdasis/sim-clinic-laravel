import { createFileRoute, useParams } from "@tanstack/react-router"
import { useQuery } from "@tanstack/react-query"

import { Skeleton } from "#/components/ui/skeleton.tsx"
import { FormAlert } from "#/components/forms/form-alert.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import type { RoleMatrixResponse } from "#/types/role-matrix.ts"
import { RoleMatrixGrid } from "./components/role-matrix-grid.tsx"

export const Route = createFileRoute("/$tenant/clinic/roles/")({
  component: RolesPage,
})

function RolesPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/roles/" })
  const { t } = useTrans()

  const roles = useQuery({
    queryKey: ["roles", tenant],
    queryFn: () =>
      apiGet<{ data: RoleMatrixResponse }>(`/${tenant}/clinic/roles`),
    retry: false,
  })

  return (
    <div>
      <div className="mb-4">
        <h1 className="text-xl font-semibold tracking-tight">
          {t("role.title")}
        </h1>
        <p className="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">
          {t("role.description")}
        </p>
      </div>

      {roles.isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      ) : roles.isError ? (
        // Peran tanpa izin `role.view` sampai di sini; alasannya disebut
        // apa adanya, bukan dibiarkan sebagai layar kosong.
        <FormAlert message={(roles.error as unknown as ApiError).message} />
      ) : roles.data ? (
        <>
          <RoleMatrixGrid tenant={tenant} data={roles.data.data} />
          <p className="mt-3 text-xs text-pretty text-muted-foreground">
            {t("role.last_admin_note")}
          </p>
        </>
      ) : null}
    </div>
  )
}

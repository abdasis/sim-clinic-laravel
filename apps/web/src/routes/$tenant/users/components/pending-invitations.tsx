import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { MailX } from "lucide-react"
import { toast } from "sonner"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface InvitationRow {
  id: number
  email: string
  role_label: string
  clinic_role_label: string | null
  expires_at: string | null
}

interface InvitationsResponse {
  data: InvitationRow[]
  meta: { total: number }
}

export function PendingInvitations({ tenant }: { tenant: string }) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const { data, isLoading, isError } = useQuery({
    queryKey: ["invitations", tenant],
    queryFn: () => apiGet<InvitationsResponse>(`/${tenant}/invitations`),
  })

  const cancel = useMutation({
    mutationFn: (id: number) =>
      apiPost(`/${tenant}/invitations/${id}/cancel`),
    onSuccess: () => {
      toast.success(t("tenant.invitation_cancelled"))
      qc.invalidateQueries({ queryKey: ["invitations"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  if (isError) return null

  return (
    <section className="mt-8">
      <div className="mb-3 flex items-center gap-2">
        <h2 className="text-sm font-semibold tracking-tight">
          {t("tenant.invitations")}
        </h2>
        {data ? (
          <Badge variant="secondary" className="tabular-nums">
            {data.meta.total}
          </Badge>
        ) : null}
      </div>

      {isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : data && data.data.length > 0 ? (
        <ul className="divide-y divide-border/60 rounded-lg border border-border/60">
          {data.data.map((invitation) => (
            <li
              key={invitation.id}
              className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
            >
              <div className="min-w-0 space-y-0.5">
                <p className="truncate text-sm font-medium">
                  {invitation.email}
                </p>
                <p className="text-xs text-muted-foreground">
                  {invitation.role_label}
                  {invitation.clinic_role_label
                    ? ` · ${invitation.clinic_role_label}`
                    : null}
                  {invitation.expires_at
                    ? ` · ${t("tenant.invitation_expires_at")} ${formatDate(invitation.expires_at)}`
                    : null}
                </p>
              </div>

              <TooltipProvider>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-8"
                      aria-label={t("tenant.cancel_invitation")}
                      disabled={cancel.isPending}
                      onClick={() => cancel.mutate(invitation.id)}
                    >
                      <MailX className="size-4" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent className="flex items-center gap-2">
                    {t("tenant.cancel_invitation")}
                    <Kbd>x</Kbd>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState
          className="rounded-lg border border-dashed border-border/60 py-8"
          illustration="users"
          title={t("tenant.no_pending_invitations")}
          description={t("tenant.no_pending_invitations_desc")}
        />
      )}
    </section>
  )
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  })
}

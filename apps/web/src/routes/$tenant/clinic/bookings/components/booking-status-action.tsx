import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import { Button } from "#/components/ui/button.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiPatch } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface BookingStatusActionProps {
  tenant: string
  booking: { id: number; status: string }
}

/** Status berikutnya yang valid dari status saat ini (FR-031). */
const NEXT_STATUS: Record<string, string | undefined> = {
  pending: "confirmed",
  confirmed: "done",
}

export function BookingStatusAction({
  tenant,
  booking,
}: BookingStatusActionProps) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: (status: string) =>
      apiPatch(`/${tenant}/clinic/bookings/${booking.id}/status`, { status }),
    onSuccess: () => {
      toast.success(t("booking.status_updated"))
      qc.invalidateQueries({ queryKey: ["bookings-schedule"] })
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
    },
  })

  const next = NEXT_STATUS[booking.status]
  const cancelDisabled =
    booking.status === "done" || booking.status === "cancelled"

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" disabled={mutation.isPending}>
          {t(`clinic.booking_status.${booking.status}`)}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {next ? (
          <DropdownMenuItem onSelect={() => mutation.mutate(next)}>
            {t(`clinic.booking_status.${next}`)}
          </DropdownMenuItem>
        ) : null}
        {next ? <DropdownMenuSeparator /> : null}
        <DropdownMenuItem
          variant="destructive"
          disabled={cancelDisabled}
          onSelect={() => mutation.mutate("cancelled")}
        >
          {t("clinic.booking_status.cancelled")}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

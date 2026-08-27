import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon } from "@hugeicons/core-free-icons"

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
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface BookingDeleteActionProps {
  tenant: string
  booking: { id: number; patient_name: string; can_delete?: boolean }
}

/**
 * Hapus permanen satu jadwal.
 *
 * Dipisah dari Batalkan dan diberi keterangan yang berbeda: membatalkan
 * menyisakan baris di kalender sebagai catatan bahwa jadwalnya pernah ada,
 * sedangkan menghapus dipakai untuk jadwal yang memang tidak pernah ada —
 * salah ketik, atau dobel.
 *
 * Booking yang sudah meninggalkan jejak tidak dibiarkan ditekan lalu ditolak
 * server: tombolnya mati dan tooltipnya menyebutkan alasannya, karena
 * penilaian boleh-tidaknya sudah ikut di setiap baris jadwal.
 */
export function BookingDeleteAction({
  tenant,
  booking,
}: BookingDeleteActionProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)

  // Tidak disebut sama sekali berarti server belum mengirimkannya; anggap
  // boleh, dan biarkan server yang jadi penjaga terakhirnya.
  const blocked = booking.can_delete === false

  const mutation = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/bookings/${booking.id}`),
    onSuccess: () => {
      toast.success(t("booking.deleted"))
      qc.invalidateQueries({ queryKey: ["bookings-schedule"] })
      setOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setOpen(false)
    },
  })

  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          {/* span pembungkus: tombol nonaktif tidak memancarkan event, jadi
              tooltip alasannya tidak akan pernah muncul tanpa ini. */}
          <span>
            <Button
              variant="ghost"
              size="icon"
              className="size-8 text-muted-foreground hover:text-destructive"
              aria-label={t("booking.delete")}
              disabled={blocked || mutation.isPending}
              onClick={() => setOpen(true)}
            >
              <HugeiconsIcon icon={Delete02Icon} className="size-4" />
            </Button>
          </span>
        </TooltipTrigger>
        <TooltipContent>
          {blocked ? t("booking.has_history_short") : t("booking.delete")}
        </TooltipContent>
      </Tooltip>

      <AlertDialog open={open} onOpenChange={setOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("booking.delete")} — {booking.patient_name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("booking.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={mutation.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={mutation.isPending}
              onClick={(event) => {
                // Dialog tidak boleh menutup sendiri sebelum servernya
                // menjawab; penutupannya diurus onSuccess/onError.
                event.preventDefault()
                mutation.mutate()
              }}
            >
              {mutation.isPending ? t("general.loading") : t("booking.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

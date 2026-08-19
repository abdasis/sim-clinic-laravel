import { useState } from "react"
import { Link } from "@tanstack/react-router"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { Ban, FileText, MoreHorizontal, Trash2 } from "lucide-react"
import { toast } from "sonner"

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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "#/components/ui/dropdown-menu.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export interface TransactionActionsRow {
  id: number
  invoice_number: string
  cancelled_at?: string | null
}

/**
 * Aksi per transaksi: buka nota, batalkan, hapus.
 *
 * Batal dan hapus dua hal berbeda dan sengaja dipisah. Membatalkan
 * mengembalikan stok dan mengeluarkan nilainya dari pemasukan, tapi
 * transaksinya tetap terlihat. Menghapus menyingkirkannya dari daftar — dan
 * karena itu server menolak menghapus transaksi yang belum dibatalkan bila
 * sudah dibayar atau memotong stok.
 */
export function TransactionActionsCell({
  tenant,
  transaction,
}: {
  tenant: string
  transaction: TransactionActionsRow
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [cancelOpen, setCancelOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["transactions"] })
    // Nilai transaksi ikut membentuk kartu ringkasan dan laporan.
    qc.invalidateQueries({ queryKey: ["stats"] })
    qc.invalidateQueries({ queryKey: ["reports"] })
  }

  const cancel = useMutation({
    mutationFn: () =>
      apiPost(`/${tenant}/clinic/transactions/${transaction.id}/cancel`),
    onSuccess: () => {
      toast.success(t("pos.cancelled"))
      refresh()
      setCancelOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setCancelOpen(false)
    },
  })

  const destroy = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/transactions/${transaction.id}`),
    onSuccess: () => {
      toast.success(t("pos.deleted"))
      refresh()
      setDeleteOpen(false)
    },
    onError: (err: ApiError) => {
      // Penolakan "batalkan dulu" datang lewat sini; pesannya sudah
      // menjelaskan langkah berikutnya, jadi cukup ditampilkan apa adanya.
      toast.error(err.message)
      setDeleteOpen(false)
    },
  })

  const isCancelled = Boolean(transaction.cancelled_at)

  return (
    <>
      <DropdownMenu>
        <Tooltip>
          <TooltipTrigger asChild>
            <DropdownMenuTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="size-8"
                aria-label={`${t("general.actions")} — ${transaction.invoice_number}`}
              >
                <MoreHorizontal className="size-4" />
              </Button>
            </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("general.actions")}
            <Kbd>.</Kbd>
          </TooltipContent>
        </Tooltip>

        <DropdownMenuContent align="end" className="min-w-48">
          <DropdownMenuItem asChild>
            <Link
              to="/$tenant/clinic/pos/invoices/$id"
              params={{ tenant, id: String(transaction.id) }}
            >
              <FileText className="size-4" />
              {t("invoice.title")}
            </Link>
          </DropdownMenuItem>

          <DropdownMenuItem
            variant="destructive"
            disabled={isCancelled}
            onSelect={() => setCancelOpen(true)}
          >
            <Ban className="size-4" />
            {t("pos.cancel_action")}
          </DropdownMenuItem>

          <DropdownMenuItem
            variant="destructive"
            onSelect={() => setDeleteOpen(true)}
          >
            <Trash2 className="size-4" />
            {t("pos.delete")}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("pos.cancel_action")} — {transaction.invoice_number}
            </AlertDialogTitle>
            <AlertDialogDescription className="text-pretty">
              {t("pos.cancel_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancel.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={cancel.isPending}
              onClick={(event) => {
                event.preventDefault()
                cancel.mutate()
              }}
            >
              {cancel.isPending ? t("general.loading") : t("pos.cancel_action")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("pos.delete")} — {transaction.invoice_number}
            </AlertDialogTitle>
            <AlertDialogDescription className="text-pretty">
              {t("pos.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={destroy.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={destroy.isPending}
              onClick={(event) => {
                event.preventDefault()
                destroy.mutate()
              }}
            >
              {destroy.isPending ? t("general.loading") : t("pos.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

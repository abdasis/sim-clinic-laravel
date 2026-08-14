import { useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { Archive, MoreHorizontal, Pencil } from "lucide-react"
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
  TooltipProvider,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import {
  ServiceFormDialog,
  type ServiceFormValues,
} from "./service-form-dialog.tsx"

export function ServiceActionsCell({
  tenant,
  service,
}: {
  tenant: string
  service: ServiceFormValues
}) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [editOpen, setEditOpen] = useState(false)
  const [archiveOpen, setArchiveOpen] = useState(false)

  const archive = useMutation({
    mutationFn: () => apiDelete(`/${tenant}/clinic/services/${service.id}`),
    onSuccess: () => {
      toast.success(t("service.archived"))
      qc.invalidateQueries({ queryKey: ["services"] })
      setArchiveOpen(false)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setArchiveOpen(false)
    },
  })

  const isArchived = service.status === "archived"

  return (
    <>
      <DropdownMenu>
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8"
                  aria-label={t("general.actions")}
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
        </TooltipProvider>

        <DropdownMenuContent align="end" className="min-w-40">
          <DropdownMenuItem onSelect={() => setEditOpen(true)}>
            <Pencil className="size-4" />
            {t("general.edit")}
            <Kbd className="ml-auto">e</Kbd>
          </DropdownMenuItem>
          <DropdownMenuItem
            variant="destructive"
            disabled={isArchived}
            onSelect={() => setArchiveOpen(true)}
          >
            <Archive className="size-4" />
            {t("service.archive")}
            <Kbd className="ml-auto">a</Kbd>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <ServiceFormDialog
        tenant={tenant}
        service={service}
        open={editOpen}
        onOpenChange={setEditOpen}
      />

      <AlertDialog open={archiveOpen} onOpenChange={setArchiveOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t("service.archive")} — {service.name}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t("service.archive_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={archive.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={archive.isPending}
              onClick={(e) => {
                e.preventDefault()
                archive.mutate()
              }}
            >
              {archive.isPending ? t("general.loading") : t("service.archive")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}

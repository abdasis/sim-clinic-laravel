import { useState } from "react"
import { Link } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Add01Icon, Refresh01Icon } from "@hugeicons/core-free-icons"

import { FormAlert } from "#/components/forms/form-alert.tsx"
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
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiErrorMessage, apiGet } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { cn } from "#/lib/utils.ts"
import type { ManagedTemplate } from "./broadcast-types.ts"
import { WhatsappTemplateRow } from "./whatsapp-template-row.tsx"

interface ManagerProps {
  tenant: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Dialog daftar template resmi Meta: tinjau status, buat, dan hapus. */
export function WhatsappTemplateManager({
  tenant,
  open,
  onOpenChange,
}: ManagerProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [pendingDelete, setPendingDelete] = useState<ManagedTemplate | null>(null)

  const templates = useQuery({
    // Satu awalan dengan pemilih broadcast, jadi invalidasi di form ikut
    // menyegarkan daftar ini tanpa kunci kedua.
    queryKey: ["broadcast-templates", tenant, "manage"],
    queryFn: () =>
      apiGet<{ data: ManagedTemplate[]; meta?: { ready?: boolean } }>(
        `/${tenant}/clinic/broadcasts/templates/manage`,
      ),
    enabled: open,
  })

  const remove = useMutation({
    mutationFn: (template: ManagedTemplate) => {
      // hsm_id hanya ada untuk template yang sudah terdaftar di Meta; nama
      // saja tetap cukup untuk membersihkan salinan lokal.
      const hsm = template.id ? `?hsm_id=${encodeURIComponent(template.id)}` : ""
      return apiDelete(
        `/${tenant}/clinic/broadcasts/templates/${encodeURIComponent(template.name)}${hsm}`,
      )
    },
    onSuccess: () => {
      toast.success(t("broadcast.template_deleted"))
      qc.invalidateQueries({ queryKey: ["broadcast-templates"] })
      setPendingDelete(null)
    },
    onError: (err: ApiError) => {
      toast.error(err.message)
      setPendingDelete(null)
    },
  })

  const rows = Array.isArray(templates.data?.data) ? templates.data.data : []
  const ready = templates.data?.meta?.ready !== false

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] gap-0 overflow-hidden p-0 sm:max-w-lg">
        <DialogHeader className="border-b border-border/50 p-4">
          <DialogTitle>{t("broadcast.template_manage")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("broadcast.template_manage_hint")}
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[58dvh] min-h-0 overflow-y-auto p-4">
          {templates.isLoading ? (
            <div className="space-y-2">
              {Array.from({ length: 3 }, (_, index) => (
                <Skeleton key={index} className="h-16 w-full rounded-md" />
              ))}
            </div>
          ) : templates.isError ? (
            <div className="space-y-2">
              <FormAlert
                message={apiErrorMessage(templates.error) ?? t("general.error")}
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="min-h-11 sm:min-h-8"
                disabled={templates.isFetching}
                onClick={() => void templates.refetch()}
              >
                {t("broadcast.template_refresh")}
              </Button>
            </div>
          ) : !ready ? (
            <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-pretty text-amber-700 dark:text-amber-400">
              {t("broadcast.template_waba_missing")}
            </p>
          ) : rows.length === 0 ? (
            <div className="rounded-md border border-dashed border-border/60 px-4 py-8 text-center">
              <p className="text-sm font-medium">{t("broadcast.template_none")}</p>
              <p className="mt-1 text-xs text-pretty text-muted-foreground">
                {t("broadcast.template_manage_hint")}
              </p>
            </div>
          ) : (
            <ul className="divide-y divide-border/50">
              {rows.map((template) => (
                <WhatsappTemplateRow
                  key={`${template.name}::${template.language}`}
                  template={template}
                  onDelete={setPendingDelete}
                />
              ))}
            </ul>
          )}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/50 p-4">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-11 sm:size-8"
                aria-label={t("broadcast.template_refresh")}
                disabled={templates.isFetching}
                onClick={() => void templates.refetch()}
              >
                <HugeiconsIcon
                  icon={Refresh01Icon}
                  strokeWidth={2}
                  className={cn("size-4", templates.isFetching && "animate-spin")}
                />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("broadcast.template_refresh")}</TooltipContent>
          </Tooltip>

          <Button
            asChild
            size="sm"
            className="min-h-11 gap-1.5 sm:min-h-8"
          >
            <Link
              to="/$tenant/clinic/broadcasts/templates/new"
              params={{ tenant }}
            >
              <HugeiconsIcon
                icon={Add01Icon}
                strokeWidth={2}
                className="size-3.5"
              />
              {t("broadcast.template_new")}
            </Link>
          </Button>
        </div>

        <AlertDialog
          open={pendingDelete !== null}
          onOpenChange={(next) => {
            if (!next) setPendingDelete(null)
          }}
        >
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>
                {t("broadcast.template_delete_confirm")}
              </AlertDialogTitle>
              <AlertDialogDescription>
                {pendingDelete
                  ? `${pendingDelete.name} (${pendingDelete.language})`
                  : ""}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={remove.isPending}>
                {t("general.cancel")}
              </AlertDialogCancel>
              <AlertDialogAction
                disabled={remove.isPending}
                onClick={(event) => {
                  // Tunggu jawaban server sebelum dialognya menutup.
                  event.preventDefault()
                  if (pendingDelete) remove.mutate(pendingDelete)
                }}
              >
                {remove.isPending ? t("general.loading") : t("general.delete")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </DialogContent>
    </Dialog>
  )
}

/**
 * Pemicu kecil yang membawa dialog kelola sendiri.
 *
 * Dipakai pemilih template dan toolbar halaman; keduanya tidak perlu tahu
 * state dialognya.
 */
export function WhatsappTemplateManageButton({
  tenant,
  variant = "link",
  className,
}: {
  tenant: string
  variant?: "link" | "outline"
  className?: string
}) {
  const { t } = useTrans()
  const [open, setOpen] = useState(false)

  return (
    <>
      <Button
        type="button"
        variant={variant}
        size="sm"
        className={className}
        onClick={() => setOpen(true)}
      >
        {t("broadcast.template_manage")}
      </Button>
      <WhatsappTemplateManager tenant={tenant} open={open} onOpenChange={setOpen} />
    </>
  )
}

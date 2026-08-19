import { useEffect, useState } from "react"
import { createFileRoute } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon, Download01Icon } from "@hugeicons/core-free-icons"

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
import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDelete, apiDownload, apiGet, apiPost } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { formatDateTime } from "#/lib/format.ts"

export const Route = createFileRoute("/central/backup/")({
  component: CentralBackupPage,
})

interface BackupFile {
  file: string
  size: number
  created_at: string
  trigger: string | null
}

/** Ukuran berkas dalam satuan yang enak dibaca manusia. */
function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`

  const units = ["KB", "MB", "GB"]
  let value = bytes / 1024
  let unit = 0

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }

  return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unit]}`
}

function CentralBackupPage() {
  const { t } = useTrans()
  const qc = useQueryClient()
  const [pendingDelete, setPendingDelete] = useState<string | null>(null)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ["central-backups"],
    queryFn: () => apiGet<{ data: BackupFile[] }>("/central/backups"),
    select: (res) => res.data,
  })

  const create = useMutation({
    mutationFn: () => apiPost("/central/backups"),
    onSuccess: () => {
      toast.success(t("backup.created"))
      qc.invalidateQueries({ queryKey: ["central-backups"] })
    },
    onError: (err: ApiError) => toast.error(err.message || t("backup.create_failed")),
  })

  const remove = useMutation({
    mutationFn: (file: string) => apiDelete(`/central/backups/${file}`),
    onSuccess: () => {
      toast.success(t("backup.deleted"))
      qc.invalidateQueries({ queryKey: ["central-backups"] })
      setPendingDelete(null)
    },
    onError: (err: ApiError) => {
      toast.error(err.message || t("backup.delete_failed"))
      setPendingDelete(null)
    },
  })

  const download = async (file: string) => {
    try {
      await apiDownload(`/central/backups/${file}/download`, {}, file)
    } catch (err) {
      toast.error((err as ApiError).message)
    }
  }

  // `b` membuat cadangan tanpa meraih tetikus. Tanpa modifier, jadi tidak
  // bertabrakan dengan pintasan bawaan browser.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null

      if (
        event.key !== "b" ||
        event.metaKey ||
        event.ctrlKey ||
        event.altKey ||
        target?.tagName === "INPUT" ||
        target?.tagName === "TEXTAREA" ||
        target?.isContentEditable
      ) {
        return
      }

      if (!create.isPending) create.mutate()
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  })

  const rows = data ?? []

  return (
    <div className="max-w-4xl">
      <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-xl font-semibold tracking-tight">
            {t("backup.title")}
          </h1>
          <p className="mt-1 max-w-2xl text-sm text-pretty text-muted-foreground">
            {t("backup.hint")}
          </p>
        </div>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              disabled={create.isPending}
              onClick={() => create.mutate()}
              className="transition-transform duration-150 ease-out hover:-translate-y-px"
            >
              {create.isPending ? t("backup.creating") : t("backup.create")}
            </Button>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("backup.create")}
            <Kbd>b</Kbd>
          </TooltipContent>
        </Tooltip>
      </header>

      {isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-9 w-full" />
          <Skeleton className="h-9 w-full" />
          <Skeleton className="h-9 w-full" />
        </div>
      ) : isError ? (
        <EmptyState
          className="py-12"
          illustration="default"
          title={t("backup.load_failed")}
          description={t("general.error")}
          action={
            <Button variant="outline" onClick={() => void refetch()}>
              {t("general.retry")}
            </Button>
          }
        />
      ) : rows.length === 0 ? (
        <EmptyState
          className="py-12"
          illustration="default"
          title={t("backup.empty_title")}
          description={t("backup.empty_desc")}
          action={
            <Button onClick={() => create.mutate()} disabled={create.isPending}>
              {t("backup.create")}
            </Button>
          }
        />
      ) : (
        <div className="overflow-hidden rounded-lg border border-border/60">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border/60 bg-muted/30 text-xs text-muted-foreground">
                <th className="px-3 py-2 text-left font-medium">
                  {t("backup.file")}
                </th>
                <th className="px-3 py-2 text-right font-medium">
                  {t("backup.size")}
                </th>
                <th className="px-3 py-2 text-left font-medium">
                  {t("backup.created_at")}
                </th>
                <th className="px-3 py-2 text-left font-medium">
                  {t("backup.trigger")}
                </th>
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr
                  key={row.file}
                  className="border-b border-border/40 transition-colors last:border-0 hover:bg-muted/20"
                >
                  <td className="px-3 py-2 font-medium break-all">{row.file}</td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {formatSize(row.size)}
                  </td>
                  <td className="px-3 py-2 whitespace-nowrap text-muted-foreground tabular-nums">
                    {formatDateTime(row.created_at)}
                  </td>
                  <td className="px-3 py-2">
                    <Badge variant="secondary" className="font-normal">
                      {row.trigger === "manual"
                        ? t("backup.manual")
                        : row.trigger === null
                          ? "-"
                          : t("backup.auto")}
                    </Badge>
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex justify-end gap-1">
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={t("backup.download")}
                            onClick={() => void download(row.file)}
                          >
                            <HugeiconsIcon
                              icon={Download01Icon}
                              className="size-4"
                            />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("backup.download")}</TooltipContent>
                      </Tooltip>

                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-destructive hover:text-destructive"
                            aria-label={t("backup.delete")}
                            onClick={() => setPendingDelete(row.file)}
                          >
                            <HugeiconsIcon
                              icon={Delete02Icon}
                              className="size-4"
                            />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("backup.delete")}</TooltipContent>
                      </Tooltip>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <AlertDialog
        open={pendingDelete !== null}
        onOpenChange={(open) => {
          if (!open) setPendingDelete(null)
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("backup.delete")}</AlertDialogTitle>
            <AlertDialogDescription className="break-all">
              {pendingDelete} — {t("backup.delete_confirm")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={remove.isPending}>
              {t("general.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              disabled={remove.isPending}
              onClick={(event) => {
                event.preventDefault()

                if (pendingDelete !== null) remove.mutate(pendingDelete)
              }}
            >
              {remove.isPending ? t("general.loading") : t("backup.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

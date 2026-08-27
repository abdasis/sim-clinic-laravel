import { useEffect, useRef, useState } from "react"
import { useMutation, useQueryClient } from "@tanstack/react-query"
import { Download, Upload } from "lucide-react"
import { toast } from "sonner"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiDownload, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

export interface ImportRowError {
  row: number
  field: string
  message: string
}

interface ImportResult {
  imported: number
  updated: number
  errors: ImportRowError[]
}

interface CatalogImportDialogProps {
  tenant: string
  /** Segmen path modul: `services` atau `products`. */
  resource: "services" | "products"
  /** Nama berkas template saat diunduh. */
  templateName: string
  /** Kunci query yang perlu disegarkan setelah impor. */
  queryKey: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Impor katalog dari template xlsx.
 *
 * Layanan dan produk memakai dialog yang sama: bedanya cuma alamat dan nama
 * berkas, sementara alurnya — unduh, isi, unggah, baca hasilnya — persis sama.
 */
export function CatalogImportDialog({
  tenant,
  resource,
  templateName,
  queryKey,
  open,
  onOpenChange,
}: CatalogImportDialogProps) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const inputRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<ImportResult | null>(null)

  // Hasil impor sebelumnya tidak boleh ikut terbaca sebagai hasil yang baru.
  useEffect(() => {
    if (open) return

    setFile(null)
    setResult(null)
  }, [open])

  const template = useMutation({
    mutationFn: () =>
      apiDownload(`/${tenant}/clinic/${resource}/import/template`, {}, templateName),
    onError: (err: ApiError) => toast.error(err.message),
  })

  const upload = useMutation({
    mutationFn: () => {
      const form = new FormData()
      form.append("file", file as File)

      return apiUpload<{ data: ImportResult }>(
        `/${tenant}/clinic/${resource}/import`,
        form,
      )
    },
    onSuccess: (response) => {
      setResult(response.data)
      qc.invalidateQueries({ queryKey: [queryKey] })
      qc.invalidateQueries({ queryKey: ["categories"] })

      const { imported, updated, errors } = response.data

      if (errors.length === 0) {
        toast.success(`${imported} ${t("import.imported")}, ${updated} ${t("import.updated")}`)
      } else {
        toast.warning(`${errors.length} ${t("import.errors")}`)
      }
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t("import.title")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("import.template_hint")}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-wrap items-center gap-2">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className="gap-1.5"
                disabled={template.isPending}
                onClick={() => template.mutate()}
              >
                <Download className="size-3.5" />
                {t("import.download_template")}
              </Button>
            </TooltipTrigger>
            <TooltipContent className="max-w-64 text-pretty">
              {t("import.name_conflict_hint")}
            </TooltipContent>
          </Tooltip>

          {/* Input berkas bawaan tidak bisa ditata; tombol di sebelahnya yang
              memicunya, seperti pola unggah gambar di modul lain. */}
          <input
            ref={inputRef}
            type="file"
            accept=".xlsx"
            className="sr-only"
            onChange={(event) => {
              setFile(event.target.files?.[0] ?? null)
              setResult(null)
            }}
          />
          <Button variant="outline" size="sm" onClick={() => inputRef.current?.click()}>
            {t("import.select_file")}
          </Button>

          {file ? (
            <span className="truncate text-xs text-muted-foreground" title={file.name}>
              {file.name}
            </span>
          ) : null}
        </div>

        {result ? (
          <div className="space-y-3 rounded-md border border-border/50 p-3">
            <div className="flex flex-wrap gap-4 text-sm">
              <span>
                {t("import.imported")}:{" "}
                <strong className="tabular-nums">{result.imported}</strong>
              </span>
              <span>
                {t("import.updated")}:{" "}
                <strong className="tabular-nums">{result.updated}</strong>
              </span>
              <span className={result.errors.length > 0 ? "text-destructive" : undefined}>
                {t("import.errors")}:{" "}
                <strong className="tabular-nums">{result.errors.length}</strong>
              </span>
            </div>

            {result.errors.length === 0 ? (
              <p className="text-xs text-muted-foreground">{t("import.no_errors")}</p>
            ) : (
              <div className="max-h-48 overflow-y-auto">
                <table className="w-full text-xs">
                  <thead className="text-muted-foreground">
                    <tr className="border-b border-border/50">
                      <th className="py-1.5 pr-3 text-left font-medium">
                        {t("import.row")}
                      </th>
                      <th className="py-1.5 pr-3 text-left font-medium">
                        {t("import.field")}
                      </th>
                      <th className="py-1.5 text-left font-medium">
                        {t("import.message")}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {result.errors.map((error, index) => (
                      <tr
                        key={`${error.row}-${error.field}-${index}`}
                        className="border-b border-border/30 last:border-0"
                      >
                        <td className="py-1.5 pr-3 tabular-nums">{error.row}</td>
                        <td className="py-1.5 pr-3 text-muted-foreground">
                          {error.field}
                        </td>
                        <td className="py-1.5 text-pretty">{error.message}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        ) : null}

        <DialogFooter>
          <Button
            className="gap-1.5"
            disabled={!file || upload.isPending}
            onClick={() => upload.mutate()}
          >
            <Upload className="size-3.5" />
            {upload.isPending ? t("general.loading") : t("import.upload")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

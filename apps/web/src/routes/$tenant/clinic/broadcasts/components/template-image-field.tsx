import { useEffect, useId, useRef, useState } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon, ImageUpload01Icon } from "@hugeicons/core-free-icons"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface TemplateImageFieldProps {
  file: File | null
  onChange: (file: File | null) => void
  hint?: string
}

/**
 * Berkas header gambar untuk template Cloud API. Hanya dirender saat template
 * terpilih memang memakai header IMAGE; berkas lama ikut dibuang begitu
 * template berganti supaya gambar promo tidak terkirim dengan template lain.
 */
export function TemplateImageField({
  file,
  onChange,
  hint,
}: TemplateImageFieldProps) {
  const { t } = useTrans()
  const id = useId()
  const inputRef = useRef<HTMLInputElement>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  // Object URL menahan berkasnya di memori sampai dilepas. Mengosongkan nilai
  // input juga penting: memilih berkas yang sama dua kali tidak memicu
  // onChange kalau nilainya masih tersimpan.
  useEffect(() => {
    if (!file) {
      setPreviewUrl(null)
      if (inputRef.current) inputRef.current.value = ""
      return
    }

    const url = URL.createObjectURL(file)
    setPreviewUrl(url)

    return () => URL.revokeObjectURL(url)
  }, [file])

  return (
    <div className="space-y-1.5">
      <label htmlFor={`${id}-file`} className="text-sm font-medium">
        {t("broadcast.image")}
      </label>

      {file && previewUrl ? (
        <div className="flex items-center gap-3 rounded-md border border-border/60 bg-muted/30 p-2">
          <img
            src={previewUrl}
            alt={file.name}
            className="size-14 shrink-0 rounded object-cover"
          />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium">{file.name}</p>
            <p className="text-xs tabular-nums text-muted-foreground">
              {Math.round(file.size / 1024)} KB
            </p>
          </div>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="size-11 shrink-0 text-muted-foreground hover:text-destructive sm:size-10"
                aria-label={t("broadcast.image_remove")}
                onClick={() => onChange(null)}
              >
                <HugeiconsIcon
                  icon={Delete02Icon}
                  strokeWidth={2}
                  className="size-4"
                />
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("broadcast.image_remove")}</TooltipContent>
          </Tooltip>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="flex min-h-11 w-full items-center gap-2 rounded-md border border-dashed border-border/70 bg-muted/20 px-3 py-3 text-left text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted/40 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
          <HugeiconsIcon
            icon={ImageUpload01Icon}
            strokeWidth={2}
            className="size-4 shrink-0"
          />
          {t("broadcast.image")}
        </button>
      )}

      <input
        id={`${id}-file`}
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png"
        className="sr-only"
        onChange={(event) => onChange(event.target.files?.[0] ?? null)}
      />

      <p className="text-xs text-muted-foreground">
        {hint ?? t("broadcast.image_hint")}
      </p>
    </div>
  )
}

import { useRef, useState } from "react"
import { useMutation } from "@tanstack/react-query"
import { Upload, X } from "lucide-react"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"

interface MediaFieldProps {
  tenant: string
  entity: string
  value?: string | null
  onChange: (path: string) => void
  disabled?: boolean
}

/**
 * Unggah berkas ke disk publik lalu simpan path-nya. Path tetap bisa
 * ditempel manual — berguna saat memindahkan konten antar lingkungan.
 */
export function MediaField({
  tenant,
  entity,
  value,
  onChange,
  disabled,
}: MediaFieldProps) {
  const { t } = useTrans()
  const inputRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(null)

  const upload = useMutation({
    mutationFn: (file: File) => {
      const form = new FormData()

      form.append("file", file)
      form.append("entity", entity)

      return apiUpload<{ data: { path: string; url: string } }>(
        `/${tenant}/clinic/company-profile/media`,
        form,
      )
    },
    onSuccess: (res) => {
      onChange(res.data.path)
      setPreview(res.data.url)
      toast.success(t("company_profile.media_uploaded"))
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <Input
          value={value ?? ""}
          onChange={(event) => onChange(event.target.value)}
          placeholder="company-profile/…"
          disabled={disabled || upload.isPending}
          className="font-mono text-xs"
        />
        <Button
          type="button"
          variant="outline"
          size="icon"
          aria-label={t("general.add")}
          disabled={disabled || upload.isPending}
          onClick={() => inputRef.current?.click()}
        >
          <Upload className="size-4" />
        </Button>
        {value ? (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={t("general.remove")}
            disabled={disabled}
            onClick={() => {
              onChange("")
              setPreview(null)
            }}
          >
            <X className="size-4" />
          </Button>
        ) : null}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(event) => {
          const file = event.target.files?.[0]

          if (file) upload.mutate(file)

          event.target.value = ""
        }}
      />

      {preview ? (
        <img
          src={preview}
          alt=""
          className="h-20 w-auto rounded-md border border-border/50 object-cover"
        />
      ) : null}
    </div>
  )
}

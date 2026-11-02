import { useEffect, useState } from "react"

import { Button } from "#/components/ui/button.tsx"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "#/components/ui/dialog.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { formatFileSize } from "./validate-file.ts"

interface CaptionDialogProps {
  file: File | null
  isSending: boolean
  error: string | null
  onCancel: () => void
  onConfirm: (caption: string) => void
}

/**
 * Keterangan opsional sebelum berkas diunggah. Audio dan stiker tidak lewat
 * dialog ini: Cloud API tidak menerima caption untuk keduanya.
 */
export function CaptionDialog({
  file,
  isSending,
  error,
  onCancel,
  onConfirm,
}: CaptionDialogProps) {
  const { t } = useTrans()
  const [caption, setCaption] = useState("")

  useEffect(() => {
    if (file) setCaption("")
  }, [file])

  return (
    <Dialog
      open={file !== null}
      onOpenChange={(next) => {
        if (!next && !isSending) onCancel()
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("inbox.attach.caption_title")}</DialogTitle>
          <DialogDescription className="text-pretty">
            {t("inbox.attach.caption_hint")}
          </DialogDescription>
        </DialogHeader>

        {file ? (
          <p
            className="truncate rounded-md border border-border/60 bg-muted/40 px-2.5 py-2 text-xs text-muted-foreground"
            title={file.name}
          >
            {file.name} · {formatFileSize(file.size)}
          </p>
        ) : null}

        <div className="grid gap-1.5">
          <Label htmlFor="attachment-caption">
            {t("inbox.attach.caption_label")}
          </Label>
          <Textarea
            id="attachment-caption"
            value={caption}
            rows={2}
            maxLength={1024}
            placeholder={t("inbox.attach.caption_placeholder")}
            disabled={isSending}
            onChange={(event) => setCaption(event.target.value)}
          />
        </div>

        {error ? (
          <p role="alert" className="text-xs text-pretty text-destructive">
            {error}
          </p>
        ) : null}

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            disabled={isSending}
            onClick={onCancel}
          >
            {t("general.cancel")}
          </Button>
          <Button
            type="button"
            disabled={isSending}
            aria-busy={isSending}
            onClick={() => onConfirm(caption)}
          >
            {isSending ? <Spinner /> : null}
            {t("inbox.attach.send")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

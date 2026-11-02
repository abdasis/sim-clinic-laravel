import { useEffect, useRef, useState, type ChangeEvent } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { AttachmentIcon } from "@hugeicons/core-free-icons"
import { toast } from "sonner"

import { Button } from "#/components/ui/button.tsx"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "#/components/ui/popover.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import type { ApiError } from "#/lib/api.ts"
import { useSendAttachment } from "../hooks/use-send-attachment.ts"
import {
  ATTACHMENT_ITEMS,
  PICKER_RULES,
  type AttachmentItem,
  type UploadTarget,
} from "./attachment/attachment-config.ts"
import { AttachmentMenuItem } from "./attachment/attachment-item.tsx"
import { CaptionDialog } from "./attachment/caption-dialog.tsx"
import { ContactDialog, type ContactValues } from "./attachment/contact-dialog.tsx"
import { QuickReplyDialog } from "./attachment/quick-reply-dialog.tsx"
import {
  attachmentSizeLimit,
  formatFileSize,
  resolveUploadKind,
  validateAttachmentFile,
} from "./attachment/validate-file.ts"

interface AttachmentMenuProps {
  tenant: string
  conversationId: number
  disabled?: boolean
  onInsertText: (text: string) => void
}

interface PickedFile {
  target: UploadTarget
  file: File
}

/**
 * Menu lampiran inbox. Berkas dipilih lewat satu input tersembunyi yang
 * `accept`-nya diganti sesuai item — kamera memakai pemilih gambar dengan
 * `capture="environment"` supaya langsung membuka kamera di ponsel.
 */
export function AttachmentMenu({
  tenant,
  conversationId,
  disabled,
  onInsertText,
}: AttachmentMenuProps) {
  const { t } = useTrans()
  const inputRef = useRef<HTMLInputElement>(null)
  const pickerRef = useRef<UploadTarget | null>(null)
  const awaitingFileRef = useRef(false)
  const [open, setOpen] = useState(false)
  const [pendingKey, setPendingKey] = useState<string | null>(null)
  const [caption, setCaption] = useState<PickedFile | null>(null)
  const [contactOpen, setContactOpen] = useState(false)
  const [quickOpen, setQuickOpen] = useState(false)

  const { media, contact } = useSendAttachment(tenant, conversationId)
  const isBusy = media.isPending || contact.isPending

  // Pemilih berkas yang dibatalkan tidak mengirim `change`. Saat jendela
  // kembali fokus tanpa berkas terpilih, status loading item dibersihkan.
  useEffect(() => {
    const handleFocus = () => {
      if (!awaitingFileRef.current) return

      window.setTimeout(() => {
        if (!awaitingFileRef.current) return

        awaitingFileRef.current = false
        setPendingKey(null)
      }, 300)
    }

    window.addEventListener("focus", handleFocus)
    return () => window.removeEventListener("focus", handleFocus)
  }, [])

  const pickFile = (item: AttachmentItem, target: UploadTarget) => {
    const input = inputRef.current
    if (!input) return

    pickerRef.current = target
    awaitingFileRef.current = true
    setPendingKey(item.key)
    input.accept = PICKER_RULES[target].accept

    if (item.action.type === "upload" && item.action.capture) {
      input.setAttribute("capture", "environment")
    } else {
      input.removeAttribute("capture")
    }

    input.value = ""
    input.click()

    // Audio dan stiker langsung diunggah: biarkan menu terbuka agar status
    // kirimnya terlihat. Sisanya membuka dialog caption lebih dulu.
    if (target !== "audio" && target !== "sticker") setOpen(false)
  }

  const openPicker = (item: AttachmentItem) => {
    if (item.action.type !== "upload") return

    pickFile(item, item.action.target)
  }

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] ?? null
    const target = pickerRef.current

    event.target.value = ""
    pickerRef.current = null
    awaitingFileRef.current = false
    setPendingKey(null)

    if (!file || !target) return

    const failure = validateAttachmentFile(target, file)

    if (failure) {
      toast.error(
        t(`inbox.attach.${failure}`).replace(
          ":size",
          formatFileSize(attachmentSizeLimit(target, file)),
        ),
      )
      return
    }

    const kind = resolveUploadKind(target, file)
    if (kind === null) return

    media.reset()

    if (target === "audio" || target === "sticker") {
      media.mutate(
        { kind, file },
        {
          onSettled: () => {
            setPendingKey(null)
            setOpen(false)
          },
        },
      )
      return
    }

    setCaption({ target, file })
  }

  const handleItem = (item: AttachmentItem) => {
    if (item.action.type === "contact") {
      setOpen(false)
      contact.reset()
      setContactOpen(true)
      return
    }

    if (item.action.type === "quick_reply") {
      setOpen(false)
      setQuickOpen(true)
      return
    }

    openPicker(item)
  }

  const handleCaption = (text: string) => {
    if (!caption) return

    const kind = resolveUploadKind(caption.target, caption.file)
    if (kind === null) return

    media.mutate(
      { kind, file: caption.file, caption: text },
      { onSuccess: () => setCaption(null) },
    )
  }

  const handleContact = (values: ContactValues) => {
    contact.mutate(values, { onSuccess: () => setContactOpen(false) })
  }

  return (
    <>
      <Popover open={open} onOpenChange={setOpen}>
        <Tooltip>
          <TooltipTrigger asChild>
            <PopoverTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-9 shrink-0 text-muted-foreground"
                aria-label={t("inbox.attach.open")}
                data-testid="attachment-button"
                disabled={disabled}
              >
                <HugeiconsIcon icon={AttachmentIcon} strokeWidth={2} />
              </Button>
            </PopoverTrigger>
          </TooltipTrigger>
          <TooltipContent>{t("inbox.attach.open")}</TooltipContent>
        </Tooltip>

        <PopoverContent side="top" align="start" sideOffset={8} className="w-60 gap-0 p-1.5 shadow-sm">
          <p className="px-2 pt-1 pb-1.5 text-[0.7rem] font-medium uppercase tracking-wide text-muted-foreground">
            {t("inbox.attach.title")}
          </p>

          {ATTACHMENT_ITEMS.map((item) => (
            <AttachmentMenuItem
              key={item.key}
              item={item}
              disabled={disabled}
              busy={isBusy}
              pending={pendingKey === item.key && media.isPending}
              onSelect={handleItem}
            />
          ))}
        </PopoverContent>
      </Popover>

      <input
        ref={inputRef}
        type="file"
        className="hidden"
        tabIndex={-1}
        aria-hidden
        onChange={handleFileChange}
      />

      <CaptionDialog
        file={caption?.file ?? null}
        isSending={media.isPending}
        error={(media.error as ApiError | null)?.message ?? null}
        onCancel={() => {
          setCaption(null)
          media.reset()
        }}
        onConfirm={handleCaption}
      />

      <ContactDialog
        open={contactOpen}
        isSending={contact.isPending}
        serverErrors={(contact.error as ApiError | null)?.errors}
        onOpenChange={setContactOpen}
        onSubmit={handleContact}
      />

      <QuickReplyDialog
        open={quickOpen}
        onOpenChange={setQuickOpen}
        onInsert={onInsertText}
      />
    </>
  )
}

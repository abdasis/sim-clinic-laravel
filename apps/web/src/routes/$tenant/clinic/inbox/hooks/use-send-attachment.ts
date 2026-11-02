import { useMutation, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"

import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, apiUpload, type ApiError } from "#/lib/api.ts"
import { threadKey } from "./use-inbox-thread.ts"
import type { UploadKind } from "../components/attachment/attachment-config.ts"
import type { Message } from "../types.ts"

interface MediaPayload {
  kind: UploadKind
  file: File
  caption?: string
}

interface ContactPayload {
  name: string
  phone: string
}

/**
 * Kirim lampiran lewat endpoint media Cloud API. Utas dan daftar percakapan
 * disegarkan setelah server menerima berkas, bukan secara optimistis: URL
 * media baru ada setelah unggahan selesai.
 */
export function useSendAttachment(tenant: string, conversationId: number) {
  const { t } = useTrans()
  const qc = useQueryClient()

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: threadKey(tenant, conversationId) })
    void qc.invalidateQueries({ queryKey: ["inbox-conversations", tenant] })
  }

  const media = useMutation({
    mutationFn: ({ kind, file, caption }: MediaPayload) => {
      const form = new FormData()

      form.append("type", kind)
      form.append("file", file)

      const trimmed = caption?.trim()
      if (trimmed) form.append("caption", trimmed)

      return apiUpload<{ data: Message; meta: unknown }>(
        `/${tenant}/clinic/inbox/conversations/${conversationId}/media`,
        form,
      )
    },
    onSuccess: () => {
      toast.success(t("inbox.attach.uploaded"))
      refresh()
    },
    onError: (error: ApiError) => {
      toast.error(error.message || t("inbox.attach.upload_failed"))
    },
  })

  const contact = useMutation({
    mutationFn: ({ name, phone }: ContactPayload) =>
      apiPost<{ data: Message; meta: unknown }>(
        `/${tenant}/clinic/inbox/conversations/${conversationId}/contact`,
        { name, phone },
      ),
    onSuccess: () => {
      toast.success(t("inbox.attach.contact.sent"))
      refresh()
    },
    onError: (error: ApiError) => {
      toast.error(error.message || t("inbox.attach.upload_failed"))
    },
  })

  return { media, contact }
}

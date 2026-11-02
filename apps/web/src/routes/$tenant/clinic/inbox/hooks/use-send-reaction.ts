import {
  useMutation,
  useQueryClient,
  type InfiniteData,
} from "@tanstack/react-query"
import { toast } from "sonner"

import { useTrans } from "#/hooks/use-trans.ts"
import { apiPost, type ApiError } from "#/lib/api.ts"
import { replaceMessage } from "../inbox-thread.ts"
import type { Message, ThreadPage } from "../types.ts"
import { threadKey } from "./use-inbox-thread.ts"

interface ReactionPayload {
  messageId: number
  emoji: string
}

/**
 * Reaksi satu pesan. Emoji kosong menghapus reaksi — itu yang dikirim saat
 * pengguna menekan emoji yang sama dua kali.
 */
export function useSendReaction(tenant: string, conversationId: number) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const key = threadKey(tenant, conversationId)

  return useMutation({
    mutationFn: ({ messageId, emoji }: ReactionPayload) =>
      apiPost<{ data: Message }>(
        `/${tenant}/clinic/inbox/conversations/${conversationId}/messages/${messageId}/reaction`,
        { emoji },
      ),
    onSuccess: (result, { messageId }) => {
      qc.setQueryData<InfiniteData<ThreadPage, number>>(key, (old) =>
        old
          ? { ...old, pages: replaceMessage(old.pages, messageId, result.data) }
          : old,
      )
    },
    onError: (error: ApiError) => {
      toast.error(error.message || t("inbox.message_menu.reaction_failed"))
    },
  })
}

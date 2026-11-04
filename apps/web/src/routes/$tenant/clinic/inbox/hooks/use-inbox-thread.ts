import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
  type InfiniteData,
} from "@tanstack/react-query"
import { useMemo } from "react"
import { toast } from "sonner"

import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPost, type ApiError } from "#/lib/api.ts"
import {
  flattenThreadPages,
  prependOutgoingMessage,
  replaceMessage,
} from "../inbox-thread.ts"
import type { Message, ThreadPage } from "../types.ts"

export function threadKey(tenant: string, conversationId: number | null) {
  return ["inbox-thread", tenant, conversationId] as const
}

export interface SendMessagePayload {
  body: string
}

/**
 * Utas satu percakapan beserta pengiriman balasan.
 *
 * Optimistis: balasan langsung muncul di ujung utas, lalu diganti pesan asli
 * saat server menjawab. Kalau gagal — mis. jendela 24 jam Cloud API sudah
 * lewat — gelembung sementaranya dibuang lagi supaya layar tidak berpura-pura
 * pesannya terkirim.
 */
export function useInboxThread(tenant: string, conversationId: number | null) {
  const { t } = useTrans()
  const qc = useQueryClient()
  const key = threadKey(tenant, conversationId)

  const query = useInfiniteQuery({
    queryKey: key,
    enabled: conversationId !== null,
    initialPageParam: 1,
    queryFn: ({ pageParam }) =>
      apiGet<ThreadPage>(
        `/${tenant}/clinic/inbox/conversations/${conversationId}`,
        { page: pageParam, per_page: 30 },
      ),
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page
        ? last.meta.current_page + 1
        : undefined,
  })

  const messages = useMemo(
    () => flattenThreadPages(query.data?.pages ?? []),
    [query.data],
  )

  const send = useMutation({
    mutationFn: ({ body }: SendMessagePayload) =>
      apiPost<{ data: Message; meta: { message: string } }>(
        `/${tenant}/clinic/inbox/conversations/${conversationId}/messages`,
        { body },
      ),
    onMutate: async ({ body }) => {
      await qc.cancelQueries({ queryKey: key })

      const previous = qc.getQueryData<InfiniteData<ThreadPage, number>>(key)
      const optimistic: Message = {
        id: -Date.now(),
        direction: "out",
        direction_label: t("inbox.direction.out"),
        body,
        status: "sent",
        status_label: t("inbox.status.sent"),
        provider_message_id: null,
        media_type: null,
        media_url: null,
        media_name: null,
        sent_at: new Date().toISOString(),
        reaction: null,
        // Gateway tidak lagi menerima kutipan baru; pesan lama tetap
        // menampilkan `reply_to` yang tersimpan di server.
        reply_to: null,
      }

      qc.setQueryData<InfiniteData<ThreadPage, number>>(key, (old) =>
        old
          ? { ...old, pages: prependOutgoingMessage(old.pages, optimistic) }
          : old,
      )

      return { previous, optimisticId: optimistic.id }
    },
    onError: (error: ApiError, _body, context) => {
      qc.setQueryData(key, context?.previous)
      toast.error(error.message)
    },
    onSuccess: (result, _body, context) => {
      const optimisticId = context?.optimisticId

      qc.setQueryData<InfiniteData<ThreadPage, number>>(key, (old) =>
        old && optimisticId !== undefined
          ? {
              ...old,
              pages: replaceMessage(old.pages, optimisticId, result.data),
            }
          : old,
      )
      qc.invalidateQueries({ queryKey: ["inbox-conversations"] })
    },
  })

  return { query, messages, send }
}

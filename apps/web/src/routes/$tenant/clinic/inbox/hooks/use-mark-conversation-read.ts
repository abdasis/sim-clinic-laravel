import { useMutation, useQueryClient } from "@tanstack/react-query"

import { apiPatch } from "#/lib/api.ts"
import type { Conversation } from "../types.ts"

/** Tandai percakapan sudah dibaca, lalu segarkan daftar agar lencananya hilang. */
export function useMarkConversationRead(tenant: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (conversationId: number) =>
      apiPatch<{ data: Conversation; meta: { message: string } }>(
        `/${tenant}/clinic/inbox/conversations/${conversationId}/read`,
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["inbox-conversations"] })
    },
  })
}

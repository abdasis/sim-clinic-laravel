import { createFileRoute, useParams } from "@tanstack/react-router"
import { useDeferredValue, useEffect, useRef, useState } from "react"

import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import { ConversationList } from "./components/conversation-list.tsx"
import {
  EmptySelection,
  MessageThread,
} from "./components/message-thread.tsx"
import { ReplyBox } from "./components/reply-box.tsx"
import { useInboxConversations } from "./hooks/use-inbox-conversations.ts"
import { useInboxThread } from "./hooks/use-inbox-thread.ts"
import { useMarkConversationRead } from "./hooks/use-mark-conversation-read.ts"

export const Route = createFileRoute("/$tenant/clinic/inbox/")({
  component: InboxPage,
})

/**
 * Kotak masuk dua kolom: daftar percakapan di kiri, utas di kanan. Di layar
 * sempit keduanya bergantian — memilih percakapan menyembunyikan daftar, dan
 * tombol kembali di kepala utas mengembalikannya.
 */
function InboxPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/inbox/" })
  const { t } = useTrans()
  const [search, setSearch] = useState("")
  const deferredSearch = useDeferredValue(search)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [draft, setDraft] = useState("")
  const markedRead = useRef<Set<number>>(new Set())

  const conversations = useInboxConversations(tenant, deferredSearch)
  const rows = conversations.data?.data ?? []
  const selected = rows.find((conversation) => conversation.id === selectedId) ?? null

  const thread = useInboxThread(tenant, selectedId)
  const { mutate: markAsRead, isPending: isMarkingRead } =
    useMarkConversationRead(tenant)

  // Pilihan yang tersaring keluar oleh pencarian tidak boleh menyandera layar
  // sempit tanpa tombol kembali; kembalikan ke daftar.
  useEffect(() => {
    if (selectedId === null || !conversations.data) return
    if (rows.some((conversation) => conversation.id === selectedId)) return

    setSelectedId(null)
  }, [conversations.data, rows, selectedId])

  // Utas baru dimuat, baru ditandai dibaca: menandai lebih dulu bisa menghapus
  // lencana untuk percakapan yang isinya ternyata gagal ditampilkan.
  useEffect(() => {
    if (!selected || selected.unread_count <= 0) return
    if (markedRead.current.has(selected.id)) return
    if (!thread.query.isSuccess) return

    markedRead.current.add(selected.id)
    markAsRead(selected.id, {
      onError: () => {
        markedRead.current.delete(selected.id)
      },
    })
  }, [markAsRead, selected, thread.query.isSuccess])

  useEffect(() => {
    setDraft("")
    thread.send.reset()
  }, [selectedId, thread.send.reset])

  const bodyError = thread.send.error?.errors?.body?.[0] ?? null

  const handleSend = () => {
    const body = draft.trim()
    if (body === "" || thread.send.isPending || selectedId === null) return

    setDraft("")
    thread.send.mutate(body, {
      onError: () => {
        // Kembalikan tulisannya: mengirim ulang lebih murah daripada mengetik
        // ulang, dan kegagalan biasanya soal jendela waktu, bukan isinya.
        setDraft(body)
      },
    })
  }

  return (
    <div className="flex h-[calc(100dvh-5rem)] min-h-96 flex-col">
      <div className="mb-3 shrink-0">
        <h1 className="text-balance text-xl font-semibold tracking-tight">
          {t("inbox.title")}
        </h1>
        <p className="text-pretty text-sm text-muted-foreground">
          {t("inbox.subtitle")}
        </p>
      </div>

      <div className="grid min-h-0 flex-1 grid-cols-1 overflow-hidden rounded-lg border border-border/50 bg-card lg:grid-cols-[20rem_minmax(0,1fr)]">
        <div
          className={cn(
            "min-h-0 border-border/50 lg:border-r",
            selectedId !== null && "hidden lg:block",
          )}
        >
          <ConversationList
            conversations={rows}
            selectedId={selectedId}
            search={search}
            isLoading={conversations.isLoading}
            isError={conversations.isError}
            error={conversations.error}
            onSelect={setSelectedId}
            onSearchChange={setSearch}
            onRetry={() => void conversations.refetch()}
          />
        </div>

        <div
          className={cn(
            "flex min-h-0 min-w-0 flex-col",
            selectedId === null && "hidden lg:flex",
          )}
        >
          {selected ? (
            <MessageThread
              conversation={selected}
              messages={thread.messages}
              isLoading={thread.query.isLoading}
              isError={thread.query.isError}
              error={thread.query.error}
              hasOlder={thread.query.hasNextPage}
              isLoadingOlder={thread.query.isFetchingNextPage}
              isMarkingRead={isMarkingRead}
              onRetry={() => void thread.query.refetch()}
              onLoadOlder={() => thread.query.fetchNextPage()}
              onMarkRead={() => markAsRead(selected.id)}
              onBack={() => setSelectedId(null)}
              footer={
                <ReplyBox
                  value={draft}
                  isSending={thread.send.isPending}
                  error={bodyError}
                  onChange={setDraft}
                  onSend={handleSend}
                />
              }
            />
          ) : (
            <EmptySelection />
          )}
        </div>
      </div>
    </div>
  )
}

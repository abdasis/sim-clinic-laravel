import type { ReactNode } from "react"
import { useMemo, useRef } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import {
  ArrowLeft02Icon,
  MessageMultiple01Icon,
  Tick02Icon,
} from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Spinner } from "#/components/ui/spinner.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useIsomorphicLayoutEffect } from "#/hooks/use-isomorphic-layout-effect.ts"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiErrorMessage } from "#/lib/api.ts"
import { groupMessagesByDay } from "../inbox-thread.ts"
import type { Conversation, Message } from "../types.ts"
import { MessageBubble } from "./message-bubble.tsx"

interface MessageThreadProps {
  conversation: Conversation
  messages: Message[]
  isLoading: boolean
  isError: boolean
  error: unknown
  hasOlder: boolean
  isLoadingOlder: boolean
  isMarkingRead: boolean
  onRetry: () => void
  onLoadOlder: () => Promise<unknown>
  onMarkRead: () => void
  onBack: () => void
  footer: ReactNode
}

export function MessageThread({
  conversation,
  messages,
  isLoading,
  isError,
  error,
  hasOlder,
  isLoadingOlder,
  isMarkingRead,
  onRetry,
  onLoadOlder,
  onMarkRead,
  onBack,
  footer,
}: MessageThreadProps) {
  const { t } = useTrans()
  const scrollRef = useRef<HTMLDivElement>(null)
  // Jarak dari dasar utas sebelum pesan lama disisipkan. Setelah DOM berubah,
  // jarak yang sama dipulihkan supaya pesan yang sedang dibaca tidak melompat.
  const keepDistance = useRef<number | null>(null)
  const days = useMemo(() => groupMessagesByDay(messages), [messages])
  const channel =
    conversation.channel_label ?? t(`inbox.channel.${conversation.channel}`)

  useIsomorphicLayoutEffect(() => {
    const element = scrollRef.current
    if (!element) return

    if (keepDistance.current !== null) {
      element.scrollTop = element.scrollHeight - keepDistance.current
      keepDistance.current = null
      return
    }

    element.scrollTop = element.scrollHeight
  }, [messages.length, isLoading])

  const loadOlder = () => {
    const element = scrollRef.current
    if (!element) return

    keepDistance.current = element.scrollHeight - element.scrollTop
    void onLoadOlder().catch(() => {
      keepDistance.current = null
    })
  }

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="flex shrink-0 items-center gap-2 border-b border-border/50 px-3 py-2">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="size-9 shrink-0 lg:hidden"
              aria-label={t("general.back")}
              data-testid="back-button"
              onClick={onBack}
            >
              <HugeiconsIcon icon={ArrowLeft02Icon} strokeWidth={2} />
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t("general.back")}</TooltipContent>
        </Tooltip>

        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">
            {conversation.contact_name?.trim() || conversation.contact_phone}
          </p>
          <p className="truncate text-xs text-muted-foreground">
            {conversation.contact_name?.trim()
              ? conversation.contact_phone
              : channel}
          </p>
        </div>

        {conversation.contact_name?.trim() ? (
          <Badge variant="outline" className="hidden shrink-0 font-normal sm:inline-flex">
            {channel}
          </Badge>
        ) : null}

        {conversation.unread_count > 0 ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="size-9 shrink-0"
                aria-label={t("inbox.mark_read")}
                data-testid="mark-read-button"
                disabled={isMarkingRead}
                onClick={onMarkRead}
              >
                {isMarkingRead ? (
                  <Spinner />
                ) : (
                  <HugeiconsIcon icon={Tick02Icon} strokeWidth={2} />
                )}
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("inbox.mark_read")}</TooltipContent>
          </Tooltip>
        ) : null}
      </div>

      <div
        ref={scrollRef}
        className="min-h-0 flex-1 overflow-y-auto scrollbar-thin"
      >
        {isLoading ? (
          <div className="space-y-3 p-4">
            <Skeleton className="ms-auto h-14 w-3/5 rounded-xl" />
            <Skeleton className="h-10 w-2/5 rounded-xl" />
            <Skeleton className="ms-auto h-10 w-1/2 rounded-xl" />
          </div>
        ) : isError ? (
          <EmptyState
            className="px-4 py-10"
            title={t("inbox.thread_load_failed")}
            description={apiErrorMessage(error) ?? t("inbox.thread_load_failed")}
            action={
              <Button variant="outline" size="sm" onClick={onRetry}>
                {t("inbox.retry")}
              </Button>
            }
          />
        ) : messages.length === 0 ? (
          <div data-testid="thread-empty">
            <EmptyState
              className="px-4 py-10"
              title="Belum ada pesan di percakapan ini"
              description="Pesan muncul begitu pasien mengirim lewat WhatsApp."
            />
          </div>
        ) : (
          <div className="flex flex-col gap-4 p-4">
            {hasOlder ? (
              <div className="flex justify-center">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={isLoadingOlder}
                  data-testid="load-older"
                  onClick={loadOlder}
                >
                  {isLoadingOlder ? t("general.loading") : "Muat pesan lebih lama"}
                </Button>
              </div>
            ) : null}

            {days.map((day) => (
              <div key={day.key} className="space-y-2">
                <div className="flex items-center gap-2">
                  <span className="h-px flex-1 bg-border/60" aria-hidden />
                  <span className="text-[0.65rem] font-medium text-muted-foreground uppercase">
                    {day.label}
                  </span>
                  <span className="h-px flex-1 bg-border/60" aria-hidden />
                </div>

                <div className="space-y-2">
                  {day.messages.map((message) => (
                    <MessageBubble key={message.id} message={message} />
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="shrink-0 border-t border-border/50 p-3">{footer}</div>
    </div>
  )
}

/** Tampilan saat layar lebar belum memilih percakapan. */
export function EmptySelection() {
  const { t } = useTrans()

  return (
    <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
      <span
        aria-hidden
        className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
      >
        <HugeiconsIcon
          icon={MessageMultiple01Icon}
          strokeWidth={2}
          className="size-5"
        />
      </span>
      <p className="max-w-64 text-sm text-pretty text-muted-foreground">
        {t("inbox.select_conversation")}
      </p>
    </div>
  )
}

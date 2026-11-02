import { HugeiconsIcon } from "@hugeicons/react"
import { MessageMultiple01Icon, Search01Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import { EmptyState } from "#/components/ui/empty-state.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiErrorMessage } from "#/lib/api.ts"
import { cn } from "#/lib/utils.ts"
import { relativeTime } from "../inbox-thread.ts"
import type { Conversation } from "../types.ts"

interface ConversationListProps {
  conversations: Conversation[]
  selectedId: number | null
  search: string
  isLoading: boolean
  isError: boolean
  error: unknown
  onSelect: (id: number) => void
  onSearchChange: (value: string) => void
  onRetry: () => void
}

export function ConversationList({
  conversations,
  selectedId,
  search,
  isLoading,
  isError,
  error,
  onSelect,
  onSearchChange,
  onRetry,
}: ConversationListProps) {
  const { t } = useTrans()

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="relative shrink-0 border-b border-border/50 p-3">
        <HugeiconsIcon
          icon={Search01Icon}
          strokeWidth={2}
          aria-hidden
          className="pointer-events-none absolute inset-y-0 start-5 my-auto size-4 text-muted-foreground"
        />
        <Input
          type="search"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          aria-label={t("inbox.search_placeholder")}
          placeholder={t("inbox.search_placeholder")}
          data-testid="conversation-search"
          className="ps-8"
        />
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto scrollbar-thin">
        {isLoading ? (
          <div className="space-y-px p-3">
            {Array.from({ length: 5 }, (_, index) => (
              <Skeleton key={index} className="h-16 w-full rounded-md" />
            ))}
          </div>
        ) : isError ? (
          <EmptyState
            className="px-4 py-10"
            title={t("inbox.load_failed")}
            description={apiErrorMessage(error) ?? t("inbox.load_failed")}
            action={
              <Button variant="outline" size="sm" onClick={onRetry}>
                {t("inbox.retry")}
              </Button>
            }
          />
        ) : conversations.length === 0 ? (
          <div data-testid="inbox-empty">
            <EmptyState
              className="px-4 py-10"
              illustration="default"
              title={t("inbox.empty")}
              description={t("inbox.empty_hint")}
            />
          </div>
        ) : (
          <ul>
            {conversations.map((conversation) => (
              <li key={conversation.id}>
                <ConversationRow
                  conversation={conversation}
                  selected={conversation.id === selectedId}
                  onSelect={onSelect}
                />
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}

function ConversationRow({
  conversation,
  selected,
  onSelect,
}: {
  conversation: Conversation
  selected: boolean
  onSelect: (id: number) => void
}) {
  const { t } = useTrans()
  const name = conversation.contact_name?.trim() || conversation.contact_phone
  const showPhone = Boolean(conversation.contact_name?.trim())
  const channel =
    conversation.channel_label ?? t(`inbox.channel.${conversation.channel}`)

  return (
    <button
      type="button"
      data-testid="conversation-row"
      aria-current={selected ? "true" : undefined}
      onClick={() => onSelect(conversation.id)}
      className={cn(
        "flex w-full items-start gap-3 border-b border-border/40 px-3 py-3 text-left transition-colors duration-150 ease-out hover:bg-muted/50 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
        selected && "bg-muted",
      )}
    >
      <span
        aria-hidden
        className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
      >
        <HugeiconsIcon
          icon={MessageMultiple01Icon}
          strokeWidth={2}
          className="size-4"
        />
      </span>

      <span className="min-w-0 flex-1">
        <span className="flex items-baseline justify-between gap-2">
          <span className="truncate text-sm font-medium">{name}</span>
          <span className="shrink-0 text-[0.65rem] text-muted-foreground tabular-nums">
            {relativeTime(conversation.last_message_at)}
          </span>
        </span>

        <span className="mt-0.5 flex items-center justify-between gap-2">
          <span className="truncate text-xs text-muted-foreground">
            {showPhone ? conversation.contact_phone : channel}
          </span>

          <span className="flex shrink-0 items-center gap-1.5">
            {showPhone ? (
              <Badge variant="outline" className="h-4 px-1.5 font-normal">
                {channel}
              </Badge>
            ) : null}
            {conversation.unread_count > 0 ? (
              <span
                data-testid="conversation-unread"
                aria-label={`${conversation.unread_count} ${t("inbox.unread")}`}
                className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[0.65rem] font-semibold text-primary-foreground tabular-nums"
              >
                {conversation.unread_count}
              </span>
            ) : null}
          </span>
        </span>
      </span>
    </button>
  )
}

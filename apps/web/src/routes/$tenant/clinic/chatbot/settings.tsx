import { useEffect, useRef, useState } from "react"
import { createFileRoute, useParams } from "@tanstack/react-router"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { HugeiconsIcon } from "@hugeicons/react"
import { BubbleChatIcon, ImageUploadIcon } from "@hugeicons/core-free-icons"

import { Avatar, AvatarFallback, AvatarImage } from "#/components/ui/avatar.tsx"
import { Button } from "#/components/ui/button.tsx"
import { Input } from "#/components/ui/input.tsx"
import { Kbd } from "#/components/ui/kbd.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { Switch } from "#/components/ui/switch.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { Label } from "#/components/ui/label.tsx"
import { useBreadcrumbTail } from "#/components/breadcrumb-tail.tsx"
import { ChatbotDiagnostics } from "./components/chatbot-diagnostics.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { apiGet, apiPut, apiUpload } from "#/lib/api.ts"
import type { ApiError } from "#/lib/api.ts"
import { useClinicIdentity } from "#/hooks/use-clinic-identity.ts"
import { BookableServicesField } from "./components/bookable-services-field.tsx"
import { ClosingMessageField } from "./components/closing-message-field.tsx"

export const Route = createFileRoute("/$tenant/clinic/chatbot/settings")({
  component: ChatbotSettingsPage,
})

interface ChatbotSettings {
  is_active: boolean
  allow_self_registration: boolean
  agent_name: string | null
  agent_avatar_url: string | null
  bookable_service_ids: number[]
  allows_stock_info: boolean
  closing_idle_minutes: number | null
  closing_message: string | null
}

function Section({
  title,
  description,
  children,
}: {
  title: string
  description: string
  children: React.ReactNode
}) {
  return (
    <section className="rounded-lg border border-border/60 bg-card p-4">
      <h2 className="text-sm font-semibold">{title}</h2>
      <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
      <div className="mt-3 space-y-3">{children}</div>
    </section>
  )
}

function ChatbotSettingsPage() {
  const { tenant } = useParams({ from: "/$tenant/clinic/chatbot/settings" })
  const { t } = useTrans()
  const qc = useQueryClient()
  const fileInput = useRef<HTMLInputElement>(null)

  const [isActive, setIsActive] = useState(false)

  const [allowSelfRegistration, setAllowSelfRegistration] = useState(false)
  const [agentName, setAgentName] = useState("")
  const [bookable, setBookable] = useState<number[]>([])
  const [allowsStockInfo, setAllowsStockInfo] = useState(true)
  const [closingMinutes, setClosingMinutes] = useState<number | null>(null)
  const [closingMessage, setClosingMessage] = useState("")

  const identity = useClinicIdentity(tenant)

  const { data, isLoading } = useQuery({
    queryKey: ["chatbot", tenant, "settings"],
    queryFn: () =>
      apiGet<{ data: ChatbotSettings }>(`/${tenant}/clinic/chatbot/settings`),
  })

  // Formulir ini bukan react-hook-form: isinya tiga kendali dengan bentuk yang
  // sangat berbeda, dan menyalakan chatbot lebih sering dilakukan tanpa
  // menyentuh yang lain.
  useEffect(() => {
    if (!data?.data) return

    setIsActive(data.data.is_active)
    setAllowSelfRegistration(data.data.allow_self_registration)
    setAgentName(data.data.agent_name ?? "")
    setBookable(data.data.bookable_service_ids ?? [])
    setAllowsStockInfo(data.data.allows_stock_info ?? true)
    setClosingMinutes(data.data.closing_idle_minutes ?? null)
    setClosingMessage(data.data.closing_message ?? "")
  }, [data])

  const save = useMutation({
    mutationFn: () =>
      apiPut(`/${tenant}/clinic/chatbot/settings`, {
        is_active: isActive,
        allow_self_registration: allowSelfRegistration,
        agent_name: agentName.trim() || null,
        bookable_service_ids: bookable,
        allows_stock_info: allowsStockInfo,
        closing_idle_minutes: closingMinutes,
        closing_message: closingMessage.trim() || null,
      }),
    onSuccess: () => {
      toast.success(t("chatbot.saved"))
      qc.invalidateQueries({ queryKey: ["chatbot", tenant, "settings"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  const uploadAvatar = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData()
      body.append("file", file)

      return apiUpload(`/${tenant}/clinic/chatbot/settings/avatar`, body)
    },
    onSuccess: () => {
      toast.success(t("chatbot.avatar_saved"))
      qc.invalidateQueries({ queryKey: ["chatbot", tenant, "settings"] })
    },
    onError: (err: ApiError) => toast.error(err.message),
  })

  // Pintasan `s` tanpa modifier — sepola dengan halaman lain, dan mati saat
  // kursor sedang berada di kolom isian.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.metaKey || event.ctrlKey || event.altKey || event.key !== "s") return

      const target = event.target as HTMLElement | null
      if (target?.isContentEditable) return
      if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return

      save.mutate()
    }

    window.addEventListener("keydown", onKey)

    return () => window.removeEventListener("keydown", onKey)
  }, [save])

  useBreadcrumbTail(t("chatbot.title"))

  if (isLoading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-9 w-64" />
        <Skeleton className="h-32 w-full rounded-lg" />
        <Skeleton className="h-72 w-full rounded-lg" />
      </div>
    )
  }

  const avatarUrl = data?.data.agent_avatar_url ?? null

  return (
    <div className="max-w-3xl space-y-4">
      <header>
        <h1 className="text-xl font-semibold tracking-tight">{t("chatbot.title")}</h1>
        <p className="mt-1 text-sm text-muted-foreground">{t("chatbot.subtitle")}</p>
      </header>

      {/* Diletakkan paling atas: yang dicari orang saat membuka halaman ini
          biasanya bukan setelannya, melainkan jawaban atas "kenapa chatbotnya
          diam". */}
      <ChatbotDiagnostics tenant={tenant} />

      <Section
        title={t("chatbot.settings")}
        description={t("chatbot.is_active_hint")}
      >
        <label className="flex items-start justify-between gap-3 rounded-md border border-border/50 p-3">
          <span className="min-w-0 space-y-1">
            <span className="block text-sm font-medium">{t("chatbot.is_active")}</span>
            <span className="block text-xs text-muted-foreground">
              {isActive ? t("chatbot.state_on") : t("chatbot.state_off")}
            </span>
          </span>
          <Switch checked={isActive} onCheckedChange={setIsActive} />
        </label>

        {/* Saklar terpisah dari is_active: klinik boleh menjawab pertanyaan
            tanpa membuka pintu tulis ke tabel pasien. */}
        <label className="flex items-start justify-between gap-3 rounded-md border border-border/50 p-3">
          <span className="min-w-0 space-y-1">
            <span className="block text-sm font-medium">
              {t("chatbot.allow_self_registration")}
            </span>
            <span className="block text-xs text-pretty text-muted-foreground">
              {t("chatbot.allow_self_registration_hint")}
            </span>
          </span>
          <Switch
            checked={allowSelfRegistration}
            onCheckedChange={setAllowSelfRegistration}
            disabled={!isActive}
          />
        </label>
      </Section>

      <Section
        title={t("chatbot.agent_section")}
        description={t("chatbot.agent_section_desc")}
      >
        <div className="flex items-start gap-4">
          <div className="space-y-2">
            <Avatar className="size-16 rounded-lg">
              {avatarUrl ? <AvatarImage src={avatarUrl} alt="" /> : null}
              <AvatarFallback className="rounded-lg bg-muted">
                <HugeiconsIcon
                  icon={BubbleChatIcon}
                  strokeWidth={2}
                  className="size-6 text-muted-foreground"
                />
              </AvatarFallback>
            </Avatar>

            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="w-full gap-1.5"
                  disabled={uploadAvatar.isPending}
                  onClick={() => fileInput.current?.click()}
                >
                  <HugeiconsIcon
                    icon={ImageUploadIcon}
                    strokeWidth={2}
                    className="size-3.5"
                  />
                  {uploadAvatar.isPending ? t("general.loading") : t("general.upload")}
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("chatbot.agent_avatar_hint")}</TooltipContent>
            </Tooltip>

            <input
              ref={fileInput}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="sr-only"
              onChange={(event) => {
                const file = event.target.files?.[0]
                if (file) uploadAvatar.mutate(file)
                event.target.value = ""
              }}
            />
          </div>

          <div className="min-w-0 flex-1 space-y-1.5">
            <Label htmlFor="chatbot-agent-name">{t("chatbot.agent_name")}</Label>
            <Input
              id="chatbot-agent-name"
              value={agentName}
              maxLength={100}
              placeholder={t("chatbot.default_agent_name")}
              onChange={(event) => setAgentName(event.target.value)}
            />
            <p className="text-xs text-muted-foreground">{t("chatbot.agent_name_hint")}</p>
          </div>
        </div>
      </Section>

      <Section
        title={t("chatbot.closing_section")}
        description={t("chatbot.closing_section_desc")}
      >
        <ClosingMessageField
          minutes={closingMinutes}
          message={closingMessage}
          clinicName={identity.data?.name ?? tenant}
          disabled={!isActive}
          onMinutesChange={setClosingMinutes}
          onMessageChange={setClosingMessage}
        />
      </Section>

      <Section
        title={t("chatbot.booking_section")}
        description={t("chatbot.booking_section_desc")}
      >
        <BookableServicesField tenant={tenant} value={bookable} onChange={setBookable} />
      </Section>

      <Section
        title={t("chatbot.stock_section")}
        description={t("chatbot.stock_section_desc")}
      >
        {/* Saklarnya tidak ikut mati saat chatbot dimatikan: ini keputusan
            kebijakan klinik soal apa yang boleh dibuka ke luar, bukan bagian
            dari cara chatbot bekerja — dan admin perlu bisa menetapkannya
            sebelum chatbotnya dinyalakan. */}
        <label className="flex items-start justify-between gap-3 rounded-md border border-border/50 p-3 transition-colors hover:bg-muted/40">
          <span className="min-w-0 space-y-1">
            <span className="block text-sm font-medium">
              {t("chatbot.allows_stock_info")}
            </span>
            <span className="block text-xs text-pretty text-muted-foreground">
              {allowsStockInfo
                ? t("chatbot.stock_state_on")
                : t("chatbot.stock_state_off")}
            </span>
          </span>
          <Tooltip>
            {/* Dibungkus span, bukan asChild langsung ke Switch: TooltipTrigger
                menimpa data-state milik Switch sehingga saklarnya tampak
                kosong padahal menyala. */}
            <TooltipTrigger asChild>
              <span className="inline-flex">
                <Switch
                  checked={allowsStockInfo}
                  onCheckedChange={setAllowsStockInfo}
                  aria-label={t("chatbot.allows_stock_info")}
                />
              </span>
            </TooltipTrigger>
            <TooltipContent className="max-w-64 text-pretty">
              {t("chatbot.allows_stock_info_hint")}
            </TooltipContent>
          </Tooltip>
        </label>
      </Section>

      <div className="sticky bottom-0 -mx-4 flex items-center justify-end gap-2 border-t border-border/50 bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              className="min-w-28"
              disabled={save.isPending}
              onClick={() => save.mutate()}
            >
              {save.isPending ? t("general.loading") : t("general.save")}
            </Button>
          </TooltipTrigger>
          <TooltipContent className="flex items-center gap-2">
            {t("general.save")}
            <Kbd>s</Kbd>
          </TooltipContent>
        </Tooltip>
      </div>
    </div>
  )
}

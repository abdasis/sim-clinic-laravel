import { HugeiconsIcon } from "@hugeicons/react"
import { Clock01Icon } from "@hugeicons/core-free-icons"

import { Input } from "#/components/ui/input.tsx"
import { Label } from "#/components/ui/label.tsx"
import { Switch } from "#/components/ui/switch.tsx"
import { Textarea } from "#/components/ui/textarea.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"

/** Jeda yang paling sering dipakai, supaya tidak perlu mengetik angka. */
const PRESETS = [5, 10, 30]

interface ClosingMessageFieldProps {
  minutes: number | null
  message: string
  clinicName: string
  disabled?: boolean
  onMinutesChange: (value: number | null) => void
  onMessageChange: (value: string) => void
}

/**
 * Ambang diam, kalimat penutup, dan pratinjaunya dalam satu blok.
 *
 * Pratinjaunya bukan hiasan: kalimat ini dikirim tanpa ada yang meninjau
 * sesaat sebelumnya, jadi satu-satunya kesempatan admin melihat hasil akhir
 * dengan nama kliniknya sudah terpasang adalah di sini.
 */
export function ClosingMessageField({
  minutes,
  message,
  clinicName,
  disabled,
  onMinutesChange,
  onMessageChange,
}: ClosingMessageFieldProps) {
  const { t } = useTrans()

  const enabled = minutes !== null
  const fallback = t("chatbot.idle_closing")

  const preview = (message.trim() || fallback)
    .replaceAll(":name", t("chatbot.idle_closing_name_fallback"))
    .replaceAll(":clinic", clinicName)

  return (
    <div className="space-y-3">
      <label className="flex items-start justify-between gap-3 rounded-md border border-border/50 p-3">
        <span className="min-w-0 space-y-1">
          <span className="block text-sm font-medium">
            {t("chatbot.closing_toggle")}
          </span>
          <span className="block text-xs text-pretty text-muted-foreground">
            {enabled ? t("chatbot.closing_enabled") : t("chatbot.closing_disabled")}
          </span>
        </span>
        <Switch
          checked={enabled}
          disabled={disabled}
          onCheckedChange={(next) => onMinutesChange(next ? 10 : null)}
        />
      </label>

      {enabled ? (
        <div className="space-y-3 rounded-md border border-border/50 p-3">
          <div className="space-y-1.5">
            <Label htmlFor="chatbot-closing-minutes">
              {t("chatbot.closing_idle_minutes")}
            </Label>
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-2">
                <Input
                  id="chatbot-closing-minutes"
                  type="number"
                  min={1}
                  max={1440}
                  inputMode="numeric"
                  value={minutes ?? ""}
                  disabled={disabled}
                  className="w-20 tabular-nums"
                  onChange={(event) => {
                    const next = Number.parseInt(event.target.value, 10)
                    onMinutesChange(Number.isNaN(next) ? null : next)
                  }}
                />
                <span className="text-xs text-muted-foreground">
                  {t("chatbot.closing_idle_minutes_unit")}
                </span>
              </div>

              <div className="ml-auto flex items-center gap-1">
                {PRESETS.map((preset) => (
                  <Tooltip key={preset}>
                    <TooltipTrigger asChild>
                      <button
                        type="button"
                        disabled={disabled}
                        aria-pressed={minutes === preset}
                        onClick={() => onMinutesChange(preset)}
                        className={cn(
                          "rounded-md border px-2 py-1 text-xs font-medium tabular-nums transition-colors",
                          "focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                          "disabled:cursor-not-allowed disabled:opacity-50",
                          minutes === preset
                            ? "border-border bg-muted text-foreground"
                            : "border-border/50 text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                        )}
                      >
                        {preset}m
                      </button>
                    </TooltipTrigger>
                    <TooltipContent>
                      {preset} {t("chatbot.closing_idle_minutes_unit")}
                    </TooltipContent>
                  </Tooltip>
                ))}
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              {t("chatbot.closing_idle_minutes_hint")}
            </p>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="chatbot-closing-message">
              {t("chatbot.closing_message")}
            </Label>
            <Textarea
              id="chatbot-closing-message"
              rows={3}
              maxLength={500}
              value={message}
              disabled={disabled}
              placeholder={fallback}
              onChange={(event) => onMessageChange(event.target.value)}
            />
            <div className="flex items-start justify-between gap-3">
              <p className="text-xs text-pretty text-muted-foreground">
                {t("chatbot.closing_message_hint")}
              </p>
              <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                {message.length}/500
              </span>
            </div>
          </div>

          {/* Gelembung chat, bukan kotak teks: yang perlu dinilai admin adalah
              bagaimana kalimatnya terbaca di layar pasien. */}
          <figure className="space-y-1.5">
            <figcaption className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <HugeiconsIcon icon={Clock01Icon} strokeWidth={2} className="size-3.5" />
              {t("chatbot.closing_preview")}
            </figcaption>
            <div className="rounded-lg rounded-tl-sm border border-border/50 bg-muted/60 px-3 py-2 text-sm leading-relaxed text-pretty">
              {preview}
            </div>
          </figure>
        </div>
      ) : null}
    </div>
  )
}

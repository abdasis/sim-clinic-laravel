import { HugeiconsIcon } from "@hugeicons/react"
import { Tick02Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"

interface BroadcastGuideProps {
  templateSelected: boolean
  slotsFilled: boolean
  recipientsReady: boolean
  titleFilled: boolean
  className?: string
}

interface GuideStep {
  title: string
  description: string
  done: boolean
}

/**
 * Panel panduan pembuatan broadcast. Statusnya mengikuti isian form, bukan
 * sekadar teks statis: langkah yang belum beres tetap muted dengan penanda
 * netral, langkah yang selesai berganti centang dan teks primary.
 */
export function BroadcastGuide({
  templateSelected,
  slotsFilled,
  recipientsReady,
  titleFilled,
  className,
}: BroadcastGuideProps) {
  const { t } = useTrans()

  const steps: GuideStep[] = [
    {
      title: t("broadcast.guide_template_title"),
      description: t("broadcast.guide_template_desc"),
      done: templateSelected,
    },
    {
      title: t("broadcast.guide_slots_title"),
      description: t("broadcast.guide_slots_desc"),
      done: slotsFilled,
    },
    {
      title: t("broadcast.guide_recipients_title"),
      description: t("broadcast.guide_recipients_desc"),
      done: recipientsReady,
    },
    {
      title: t("broadcast.guide_send_title"),
      description: t("broadcast.guide_send_desc"),
      done: titleFilled,
    },
  ]

  const allDone = steps.every((step) => step.done)

  return (
    <aside
      aria-label={t("broadcast.guide_title")}
      className={cn(
        "rounded-lg border border-border/50 bg-card p-4",
        className,
      )}
    >
      <h2 className="text-sm font-semibold tracking-tight">
        {t("broadcast.guide_title")}
      </h2>
      <p className="mt-1 text-xs leading-relaxed text-muted-foreground text-pretty">
        {t("broadcast.guide_intro")}
      </p>

      <ol className="mt-3 space-y-3">
        {steps.map((step) => (
          <li key={step.title} className="flex gap-2.5">
            <span
              aria-hidden
              className={cn(
                "mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border transition-colors duration-150",
                step.done
                  ? "border-primary bg-primary text-primary-foreground"
                  : "border-border text-muted-foreground",
              )}
            >
              {step.done ? (
                <HugeiconsIcon
                  icon={Tick02Icon}
                  strokeWidth={2.5}
                  className="size-2.5"
                />
              ) : null}
            </span>
            <div className="min-w-0">
              <p
                className={cn(
                  "text-xs font-medium",
                  step.done ? "text-primary" : "text-muted-foreground",
                )}
              >
                {step.title}
              </p>
              <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground text-pretty">
                {step.description}
              </p>
            </div>
          </li>
        ))}
      </ol>

      <div className="mt-4 border-t border-border/50 pt-3">
        <p
          className={cn(
            "flex items-center gap-1.5 text-xs font-medium",
            allDone ? "text-primary" : "text-muted-foreground",
          )}
        >
          {allDone ? (
            <HugeiconsIcon
              icon={Tick02Icon}
              strokeWidth={2.5}
              className="size-3.5"
            />
          ) : null}
          {allDone ? t("broadcast.guide_ready") : t("broadcast.guide_not_ready")}
        </p>
      </div>
    </aside>
  )
}

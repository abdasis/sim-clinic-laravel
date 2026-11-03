import { HugeiconsIcon } from "@hugeicons/react"
import { Tick02Icon } from "@hugeicons/core-free-icons"

import { cn } from "#/lib/utils.ts"

export interface GuideStep {
  title: string
  description: string
  done: boolean
}

interface GuidePanelProps {
  title: string
  intro: string
  steps: GuideStep[]
  readyLabel: string
  notReadyLabel: string
  note?: string
  className?: string
}

/**
 * Panel panduan bersama. Status tiap langkah mengikuti isian form, bukan
 * teks statis: yang belum beres tetap muted, yang selesai berganti centang.
 */
export function GuidePanel({
  title,
  intro,
  steps,
  readyLabel,
  notReadyLabel,
  note,
  className,
}: GuidePanelProps) {
  const allDone = steps.every((step) => step.done)

  return (
    <aside
      aria-label={title}
      className={cn("rounded-lg border border-border/50 bg-card p-4", className)}
    >
      <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
      <p className="mt-1 text-xs leading-relaxed text-muted-foreground text-pretty">
        {intro}
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
          {allDone ? readyLabel : notReadyLabel}
        </p>
        {note ? (
          <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground text-pretty">
            {note}
          </p>
        ) : null}
      </div>
    </aside>
  )
}

import { Fragment } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { Image01Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import { placeholderNumbers } from "./template-variables.ts"

const SLOT = /(\{\{\s*\d+\s*\}\})/

interface TemplatePreviewProps {
  body: string
  params: string[]
  hasImage?: boolean
  className?: string
}

/**
 * Pratinjau gelembung WhatsApp dengan slot {{n}} diganti nama datanya.
 *
 * Angka slot tidak berarti apa-apa bagi orang awam; menampilkan
 * "[Nama pasien]" membuat hasil akhirnya langsung terbaca.
 */
export function TemplatePreview({
  body,
  params,
  hasImage = false,
  className,
}: TemplatePreviewProps) {
  const { t } = useTrans()
  const slots = placeholderNumbers(body)

  const sample = (part: string) => {
    const number = Number(part.replace(/[^\d]/g, ""))
    const index = slots.indexOf(number)
    const variable = index >= 0 ? params[index] : undefined

    return variable
      ? `[${t(`broadcast.template_variable_${variable}`)}]`
      : `[data ${number}]`
  }

  return (
    <section
      className={cn(
        "rounded-lg border border-border/50 bg-card p-4 sm:p-5",
        className,
      )}
    >
      <h2 className="text-sm font-semibold tracking-tight">
        {t("broadcast.template_preview")}
      </h2>

      <div className="mt-3 rounded-lg border border-border/50 bg-muted/40 p-3">
        <div className="rounded-lg border border-border/50 bg-background p-3 text-[13px] leading-relaxed">
          {hasImage ? (
            <div className="mb-2 flex h-24 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border/60 bg-muted/30 text-muted-foreground">
              <HugeiconsIcon
                icon={Image01Icon}
                strokeWidth={2}
                className="size-5"
                aria-hidden
              />
              <span className="text-xs">{t("broadcast.image")}</span>
            </div>
          ) : null}

          {body.trim() ? (
            <p className="whitespace-pre-wrap text-pretty">
              {body.split(SLOT).map((part, index) =>
                SLOT.test(part) ? (
                  <span
                    key={`${index}-${part}`}
                    className="mx-0.5 inline-flex rounded-sm bg-primary/10 px-1 text-primary"
                  >
                    {sample(part)}
                  </span>
                ) : (
                  <Fragment key={`${index}-${part}`}>{part}</Fragment>
                ),
              )}
            </p>
          ) : (
            <p className="text-xs text-pretty text-muted-foreground">
              {t("broadcast.template_body_hint")}
            </p>
          )}
        </div>
      </div>
    </section>
  )
}

import { Fragment } from "react"
import { HugeiconsIcon } from "@hugeicons/react"
import { Image01Icon } from "@hugeicons/core-free-icons"

import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import { TEMPLATE_VARIABLES } from "./template-picker.tsx"

const SLOT = /(\{\{\s*\d+\s*\}\})/

interface TemplatePreviewProps {
  headerType: string
  headerText: string
  body: string
  className?: string
}

/**
 * Pratinjau gelembung WhatsApp dengan slot {{n}} diganti nama datanya.
 *
 * Angka slot tidak berarti apa-apa bagi orang awam; menampilkan
 * "[Nama pasien]" membuat hasil akhirnya langsung terbaca.
 */
export function TemplatePreview({
  headerType,
  headerText,
  body,
  className,
}: TemplatePreviewProps) {
  const { t } = useTrans()

  const sample = (part: string) => {
    const number = Number(part.replace(/[^\d]/g, ""))
    const variable = TEMPLATE_VARIABLES[number - 1]

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
        {t("broadcast.template_preview_label")}
      </h2>

      <div className="mt-3 rounded-lg border border-border/50 bg-muted/40 p-3">
        <div className="rounded-lg border border-border/50 bg-background p-3 text-[13px] leading-relaxed">
          {headerType === "IMAGE" ? (
            <div className="mb-2 flex h-24 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border/60 bg-muted/30 text-muted-foreground">
              <HugeiconsIcon
                icon={Image01Icon}
                strokeWidth={2}
                className="size-5"
                aria-hidden
              />
              <span className="text-xs">{t("broadcast.template_header_image")}</span>
            </div>
          ) : null}

          {headerType === "TEXT" && headerText.trim() ? (
            <p className="mb-1.5 font-semibold text-pretty">{headerText}</p>
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
              {t("broadcast.template_section_body_hint")}
            </p>
          )}
        </div>
      </div>
    </section>
  )
}

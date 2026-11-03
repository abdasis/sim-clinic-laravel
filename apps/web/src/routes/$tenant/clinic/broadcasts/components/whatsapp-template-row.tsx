import { HugeiconsIcon } from "@hugeicons/react"
import { Delete02Icon } from "@hugeicons/core-free-icons"

import { Badge } from "#/components/ui/badge.tsx"
import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"
import type { ManagedTemplate } from "./broadcast-types.ts"

/** Warna lencana status mengikuti maknanya, bukan warna acak per kategori. */
function statusClass(status: string): string {
  switch (status.toUpperCase()) {
    case "APPROVED":
      return "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
    case "PENDING":
      return "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400"
    case "REJECTED":
      return "border-destructive/30 bg-destructive/10 text-destructive"
    default:
      return "border-border text-muted-foreground"
  }
}

interface WhatsappTemplateRowProps {
  template: ManagedTemplate
  onDelete: (template: ManagedTemplate) => void
}

/** Satu baris template: identitas, status approval, alasan tolak, hapus. */
export function WhatsappTemplateRow({
  template,
  onDelete,
}: WhatsappTemplateRowProps) {
  const { t } = useTrans()

  const statusLabel = (status: string) => {
    switch (status.toUpperCase()) {
      case "APPROVED":
        return t("broadcast.template_status_approved")
      case "PENDING":
        return t("broadcast.template_status_pending")
      case "REJECTED":
        return t("broadcast.template_status_rejected")
      default:
        return status
    }
  }

  const categoryLabel = (category: string) => {
    switch (category.toUpperCase()) {
      case "MARKETING":
        return t("broadcast.template_category_marketing")
      case "UTILITY":
        return t("broadcast.template_category_utility")
      default:
        return category
    }
  }

  return (
    <li className="flex items-start gap-2 py-3 first:pt-0 last:pb-0">
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-1.5">
          <p
            className="truncate font-mono text-sm font-medium"
            title={template.name}
          >
            {template.name}
          </p>
          <Badge
            variant="outline"
            className={cn("font-normal", statusClass(template.status))}
          >
            {statusLabel(template.status)}
          </Badge>
        </div>
        <p className="text-xs text-muted-foreground">
          {template.language} · {categoryLabel(template.category)}
        </p>
        <p className="line-clamp-2 text-xs whitespace-pre-wrap text-pretty text-muted-foreground">
          {template.body}
        </p>
        {template.rejected_reason ? (
          <p className="text-xs text-pretty text-destructive">
            {t("broadcast.template_rejected_reason")}: {template.rejected_reason}
          </p>
        ) : null}
      </div>

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="size-11 shrink-0 text-muted-foreground hover:text-destructive sm:size-10"
            aria-label={`${t("general.delete")} ${template.name}`}
            onClick={() => onDelete(template)}
          >
            <HugeiconsIcon
              icon={Delete02Icon}
              strokeWidth={2}
              className="size-4"
            />
          </Button>
        </TooltipTrigger>
        <TooltipContent>{t("general.delete")}</TooltipContent>
      </Tooltip>
    </li>
  )
}

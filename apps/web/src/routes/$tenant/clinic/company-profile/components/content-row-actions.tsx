import { Link } from "@tanstack/react-router"
import { ArrowDown, ArrowUp, Eye, EyeOff, Pencil, Trash2 } from "lucide-react"

import { Button } from "#/components/ui/button.tsx"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "#/components/ui/tooltip.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface ContentRowActionsProps {
  tenant: string
  entity: string
  id: number
  isActive: boolean
  isFirst: boolean
  isLast: boolean
  busy?: boolean
  onMove: (delta: number) => void
  onToggle: () => void
  onDelete: () => void
}

/** Aksi per baris daftar CMS: urutkan, tampilkan/sembunyikan, ubah, hapus. */
export function ContentRowActions({
  tenant,
  entity,
  id,
  isActive,
  isFirst,
  isLast,
  busy,
  onMove,
  onToggle,
  onDelete,
}: ContentRowActionsProps) {
  const { t } = useTrans()

  return (
    <div className="flex items-center justify-end gap-0.5">
      <IconAction
        label={t("company_profile.sort_order")}
        disabled={isFirst || busy}
        onClick={() => onMove(-1)}
      >
        <ArrowUp className="size-4" />
      </IconAction>
      <IconAction
        label={t("company_profile.sort_order")}
        disabled={isLast || busy}
        onClick={() => onMove(1)}
      >
        <ArrowDown className="size-4" />
      </IconAction>
      <IconAction
        label={
          isActive ? t("company_profile.unpublish") : t("company_profile.publish")
        }
        disabled={busy}
        onClick={onToggle}
      >
        {isActive ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
      </IconAction>

      <Tooltip>
        <TooltipTrigger asChild>
          <Button asChild variant="ghost" size="icon" className="size-8">
            <Link
              to="/$tenant/clinic/company-profile/$entity/$id"
              params={{ tenant, entity, id: String(id) }}
              aria-label={t("general.edit")}
            >
              <Pencil className="size-4" />
            </Link>
          </Button>
        </TooltipTrigger>
        <TooltipContent className="flex items-center gap-2">
          {t("general.edit")}
          <kbd className="rounded-sm bg-background/20 px-1 font-mono text-[10px]">
            e
          </kbd>
        </TooltipContent>
      </Tooltip>

      <IconAction label={t("general.delete")} destructive onClick={onDelete}>
        <Trash2 className="size-4" />
      </IconAction>
    </div>
  )
}

function IconAction({
  label,
  destructive,
  disabled,
  onClick,
  children,
}: {
  label: string
  destructive?: boolean
  disabled?: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          aria-label={label}
          disabled={disabled}
          onClick={onClick}
          className={destructive ? "size-8 text-destructive" : "size-8"}
        >
          {children}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

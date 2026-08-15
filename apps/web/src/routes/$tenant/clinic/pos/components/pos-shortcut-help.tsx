import { Kbd, KbdGroup } from "#/components/ui/kbd.tsx"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "#/components/ui/sheet.tsx"
import { useTrans } from "#/hooks/use-trans.ts"

interface PosShortcutHelpProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Daftar pintasan yang benar-benar aktif; ditulis sekali, dibaca di dua tempat. */
export function PosShortcutHelp({ open, onOpenChange }: PosShortcutHelpProps) {
  const { t } = useTrans()

  const rows: Array<{ keys: string[]; label: string }> = [
    { keys: ["/"], label: t("pos.shortcut.search") },
    { keys: ["Enter"], label: t("pos.shortcut.add") },
    { keys: ["+", "-"], label: t("pos.shortcut.qty") },
    { keys: ["Esc"], label: t("pos.shortcut.clear") },
    { keys: ["Mod", "Enter"], label: t("pos.shortcut.save") },
    { keys: ["?"], label: t("pos.shortcut.help") },
  ]

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-sm">
        <SheetHeader>
          <SheetTitle>{t("pos.shortcut.title")}</SheetTitle>
          <SheetDescription>{t("pos.shortcut.open")}</SheetDescription>
        </SheetHeader>

        <dl className="divide-y divide-border/50 px-4">
          {rows.map((row) => (
            <div
              key={row.label}
              className="flex items-center justify-between gap-4 py-2.5"
            >
              <dt className="text-sm text-muted-foreground">{row.label}</dt>
              <dd>
                <KbdGroup>
                  {row.keys.map((key) => (
                    <Kbd key={key}>{key}</Kbd>
                  ))}
                </KbdGroup>
              </dd>
            </div>
          ))}
        </dl>
      </SheetContent>
    </Sheet>
  )
}

import { HugeiconsIcon } from "@hugeicons/react"
import { Tick02Icon } from "@hugeicons/core-free-icons"

import { Label } from "#/components/ui/label.tsx"
import { Skeleton } from "#/components/ui/skeleton.tsx"
import { useTrans } from "#/hooks/use-trans.ts"
import { cn } from "#/lib/utils.ts"

export interface StaffOption {
  id: number
  name: string
  clinic_role: string
}

interface PerformerPickerProps {
  staff: StaffOption[]
  value: number[]
  onChange: (next: number[]) => void
  loading?: boolean
}

/**
 * Pemilih pelaksana kunjungan. Sengaja daftar tombol, bukan dropdown pilihan
 * ganda: jumlah staf klinik sedikit, dan di panel selebar 380px membuka popup
 * hanya untuk mencentang dua nama lebih lambat daripada menekannya langsung.
 * Yang terpilih juga tetap terlihat tanpa harus membuka apa pun.
 */
export function PerformerPicker({
  staff,
  value,
  onChange,
  loading,
}: PerformerPickerProps) {
  const { t } = useTrans()

  const toggle = (id: number) =>
    onChange(
      value.includes(id) ? value.filter((it) => it !== id) : [...value, id],
    )

  return (
    <div className="space-y-2">
      {/* Label biasa, bukan FieldLabel: pemilih ini bukan field
          react-hook-form, dan FormLabel menuntut konteks field yang tidak
          ada di sini. */}
      <Label>{t("pos.performers")}</Label>

      {loading ? (
        // Kerangka sebentuk kepingnya, bukan satu baris teks: kasir sedang
        // menunggu sesuatu yang bentuknya sudah bisa ditebak, dan pergantian
        // dari teks ke deretan keping membuat panelnya melompat.
        <div className="flex flex-wrap gap-1.5">
          <Skeleton className="h-[26px] w-24 rounded-full" />
          <Skeleton className="h-[26px] w-20 rounded-full" />
          <Skeleton className="h-[26px] w-28 rounded-full" />
        </div>
      ) : staff.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t("pos.no_performers")}</p>
      ) : (
        <div className="flex flex-wrap gap-1.5">
          {staff.map((member) => {
            const selected = value.includes(member.id)

            return (
              <button
                key={member.id}
                type="button"
                aria-pressed={selected}
                onClick={() => toggle(member.id)}
                className={cn(
                  "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                  selected
                    ? "border-primary/40 bg-primary/10 text-foreground"
                    : "border-border/60 text-muted-foreground hover:border-border hover:text-foreground",
                )}
              >
                {selected ? (
                  <HugeiconsIcon
                    icon={Tick02Icon}
                    strokeWidth={2.5}
                    className="size-3"
                  />
                ) : null}
                {member.name}
              </button>
            )
          })}
        </div>
      )}

      <p className="text-xs text-muted-foreground">
        {t("pos.performers_hint")}
      </p>
    </div>
  )
}

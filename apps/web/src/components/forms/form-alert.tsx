import { HugeiconsIcon } from "@hugeicons/react"
import { Alert02Icon } from "@hugeicons/core-free-icons"

/**
 * Pesan gagal yang tidak menempel pada satu field — izin ditolak, server
 * bermasalah, sambungan putus.
 *
 * Toast saja tidak cukup: ia muncul di pojok layar sementara mata pengguna
 * sedang di dalam form, dan hilang sendiri sebelum sempat dibaca. Yang ini
 * bertahan di tempat kejadian sampai percobaan berikutnya.
 */
export function FormAlert({ message }: { message?: string | null }) {
  if (!message) return null

  return (
    <div
      role="alert"
      className="flex items-start gap-2.5 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2.5"
    >
      <HugeiconsIcon
        icon={Alert02Icon}
        strokeWidth={2}
        className="mt-0.5 size-4 shrink-0 text-destructive"
      />
      <p className="text-sm leading-relaxed text-destructive">{message}</p>
    </div>
  )
}

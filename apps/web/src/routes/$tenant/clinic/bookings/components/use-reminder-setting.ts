import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"

export interface ReminderSetting {
  is_active: boolean
  offset_minutes: number
  message_template_id: number | null
  confirmation_template_id: number | null
}

export const reminderSettingKey = (tenant: string) =>
  ["booking-reminder-setting", tenant] as const

/**
 * Setelan pengingat berlaku satu per klinik, dipakai di dua tempat: dialog
 * setelannya sendiri dan form booking — yang perlu tahu apakah saklarnya
 * menyala sebelum menawarkan penanda per booking.
 */
export function useReminderSetting(tenant: string, enabled = true) {
  return useQuery({
    queryKey: reminderSettingKey(tenant),
    queryFn: () =>
      apiGet<{ data: ReminderSetting }>(
        `/${tenant}/clinic/bookings/reminder-settings`,
      ),
    enabled,
  })
}

/**
 * Ubah menit jadi kalimat yang dibaca admin, bukan angka mentah: "90" tidak
 * langsung terbaca sebagai satu setengah jam saat dipakai sehari-hari.
 */
export function offsetLabel(minutes: number): string {
  if (minutes < 60) return `${minutes} menit sebelum jadwal`

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  if (hours < 24) {
    return rest === 0
      ? `${hours} jam sebelum jadwal`
      : `${hours} jam ${rest} menit sebelum jadwal`
  }

  const days = Math.floor(hours / 24)
  const restHours = hours % 24

  return restHours === 0
    ? `${days} hari sebelum jadwal`
    : `${days} hari ${restHours} jam sebelum jadwal`
}

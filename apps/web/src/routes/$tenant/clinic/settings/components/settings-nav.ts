import type { IconSvgElement } from "@hugeicons/react"
import {
  AiChat01Icon,
  ClockIcon,
  PaintBoardIcon,
  SecurityLockIcon,
  Store01Icon,
  UserGroupIcon,
  WhatsappIcon,
} from "@hugeicons/core-free-icons"

export interface SettingsNavItem {
  /** Sufiks alamat setelah `/settings`; string kosong untuk Profil klinik. */
  match: string
  to:
    | "/$tenant/clinic/settings"
    | "/$tenant/clinic/settings/whatsapp"
    | "/$tenant/clinic/settings/chatbot"
    | "/$tenant/clinic/settings/users"
    | "/$tenant/clinic/settings/roles"
    | "/$tenant/clinic/settings/preferences"
    | "/$tenant/clinic/settings/activity-logs"
  labelKey: string
  icon: IconSvgElement
  /** Hanya admin klinik yang melihat dan boleh membuka halaman ini. */
  adminOnly?: boolean
  permission?: string
}

export const SETTINGS_ITEMS: SettingsNavItem[] = [
  {
    match: "",
    to: "/$tenant/clinic/settings",
    labelKey: "settings.nav.profile",
    icon: Store01Icon,
  },
  {
    match: "whatsapp",
    to: "/$tenant/clinic/settings/whatsapp",
    labelKey: "settings.nav.whatsapp",
    icon: WhatsappIcon,
    adminOnly: true,
  },
  {
    match: "chatbot",
    to: "/$tenant/clinic/settings/chatbot",
    labelKey: "chatbot.title",
    icon: AiChat01Icon,
    adminOnly: true,
    permission: "content.view",
  },
  {
    match: "users",
    to: "/$tenant/clinic/settings/users",
    labelKey: "settings.nav.users",
    icon: UserGroupIcon,
    adminOnly: true,
    permission: "staff.view",
  },
  {
    match: "roles",
    to: "/$tenant/clinic/settings/roles",
    labelKey: "role.title",
    icon: SecurityLockIcon,
    adminOnly: true,
    permission: "role.view",
  },
  {
    match: "preferences",
    to: "/$tenant/clinic/settings/preferences",
    labelKey: "settings.nav.preferences",
    icon: PaintBoardIcon,
  },
  {
    match: "activity-logs",
    to: "/$tenant/clinic/settings/activity-logs",
    labelKey: "activity_log.title",
    icon: ClockIcon,
    adminOnly: true,
    permission: "activity_log.view",
  },
]

/**
 * Menyaring item sidebar sesuai peran dan izin. `permissions` bernilai null
 * selama `/me` belum selesai dimuat; ketika itu hanya gerbang peran yang
 * berlaku supaya daftar tidak mendadak kosong.
 */
export function visibleSettingsItems(
  items: SettingsNavItem[],
  role: string,
  permissions: string[] | null,
): SettingsNavItem[] {
  return items.filter((item) => {
    if (item.adminOnly && role !== "admin") return false
    return (
      !item.permission ||
      permissions === null ||
      permissions.includes(item.permission)
    )
  })
}

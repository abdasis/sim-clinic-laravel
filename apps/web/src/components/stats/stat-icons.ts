import {
  Alert01Icon,
  ArchiveIcon,
  ArrowDown01Icon,
  ArrowUp01Icon,
  Calendar01Icon,
  CalendarCheckIcon,
  Cancel01Icon,
  CheckmarkCircle02Icon,
  Clock01Icon,
  CoinsDollarIcon,
  DeliveryBox01Icon,
  Notebook01Icon,
  PackageIcon,
  ReceiptDollarIcon,
  SparklesIcon,
  TagsIcon,
  ToggleOnIcon,
  UserAdd01Icon,
  UserGroupIcon,
  UserMultipleIcon,
  Wallet01Icon,
} from "@hugeicons/core-free-icons"
import type { IconSvgElement } from "@hugeicons/react"

/**
 * Ikon per kunci KPI. Kuncinya sengaja bermakna sama lintas modul
 * ("total", "active", "archived"), jadi satu peta cukup untuk sebelas halaman
 * dan halaman index tidak perlu ikut mengurus ikon.
 */
const ICONS: Record<string, IconSvgElement> = {
  // Umum
  total: UserGroupIcon,
  active: CheckmarkCircle02Icon,
  archived: ArchiveIcon,
  inactive: ToggleOnIcon,

  // Pasien
  new_7d: UserAdd01Icon,
  new_30d: UserMultipleIcon,

  // Layanan & produk
  avg_price: TagsIcon,
  low_stock: Alert01Icon,

  // Inventaris
  movements: DeliveryBox01Icon,
  total_in: ArrowUp01Icon,
  total_out: ArrowDown01Icon,

  // Rekam medis
  this_week: Calendar01Icon,
  authors: Notebook01Icon,

  // Transaksi
  revenue: CoinsDollarIcon,
  outstanding: Wallet01Icon,
  paid_count: ReceiptDollarIcon,
  cancelled_count: Cancel01Icon,

  // Booking
  today: CalendarCheckIcon,
  pending: Clock01Icon,
  done: CheckmarkCircle02Icon,

  // Staff & platform
  roles_filled: UserMultipleIcon,
  new_this_month: SparklesIcon,
}

/** Kunci yang tidak dikenal tetap dapat ikon, bukan kartu tanpa ikon. */
export function statIcon(key: string): IconSvgElement {
  return ICONS[key] ?? PackageIcon
}

/** KPI yang pantas disorot merah ketika nilainya di atas nol. */
const ALERT_KEYS = new Set(["low_stock", "outstanding", "cancelled_count"])

export function isAlertKpi(key: string, value: number): boolean {
  return ALERT_KEYS.has(key) && value > 0
}

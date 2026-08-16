import {
  Award01Icon,
  CheckmarkBadge01Icon,
  CpuIcon,
  Diamond01Icon,
  FavouriteIcon,
  Home01Icon,
  Location01Icon,
  MedicalMaskIcon,
  SmileIcon,
  SparklesIcon,
  StethoscopeIcon,
  Time01Icon,
  UserGroupIcon,
  Shield01Icon,
} from "@hugeicons/core-free-icons"
import type { IconSvgElement } from "@hugeicons/react"

/**
 * Ikon per kunci keunggulan. Kuncinya dipilih admin lewat CMS, jadi petanya
 * longgar: nama yang tidak dikenal jatuh ke ikon netral, bukan bikin halaman
 * kosong.
 *
 * Sebelumnya nama kuncinya dipotong dua huruf lalu ditulis apa adanya —
 * "award" tampil sebagai kotak berisi "AW". Yang terbaca pengunjung bukan
 * ikon, melainkan singkatan yang tidak berarti apa-apa.
 */
const ICONS: Record<string, IconSvgElement> = {
  award: Award01Icon,
  smile: SmileIcon,
  cpu: CpuIcon,
  clock: Time01Icon,
  time: Time01Icon,
  map: Location01Icon,
  location: Location01Icon,
  "shield-check": CheckmarkBadge01Icon,
  shield: Shield01Icon,
  doctor: StethoscopeIcon,
  stethoscope: StethoscopeIcon,
  mask: MedicalMaskIcon,
  heart: FavouriteIcon,
  sparkles: SparklesIcon,
  diamond: Diamond01Icon,
  team: UserGroupIcon,
  home: Home01Icon,
}

export function valuePropIcon(name?: string | null): IconSvgElement {
  if (!name) return SparklesIcon

  return ICONS[name.toLowerCase()] ?? SparklesIcon
}

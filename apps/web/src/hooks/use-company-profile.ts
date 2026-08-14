import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"
import type { ContentLocale, Translatable } from "#/lib/company-locale.ts"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"

/** Teks pendek dua bahasa. */
type Text = Translatable<string>
/** Rich text Tiptap dua bahasa. */
type RichText = Translatable<RichTextDoc>

export interface ChatChannel {
  type: string
  url: string
  label?: Text
}

export interface SocialLink {
  platform: string
  url: string
  icon?: string
}

export interface MarketplaceLink {
  name: string
  url: string
  icon?: string
}

export interface CompanySettings {
  site_name?: Text | null
  logo_path?: string | null
  logo_url?: string | null
  copyright_text?: string | null
  chat_channels: ChatChannel[]
  social_links: SocialLink[]
  marketplace_links: MarketplaceLink[]
  default_locale: string
  is_published: boolean
}

export interface CompanyNavItem {
  id: number
  label?: Text | null
  url?: string | null
  link_type: string
  position: string
  is_cta: boolean
}

export interface CompanySlide {
  id: number
  title?: Text | null
  subtitle?: Text | null
  image_url?: string | null
  cta_label?: Text | null
  cta_url?: string | null
  cta_type?: string | null
}

export interface CompanyValueProp {
  id: number
  icon?: string | null
  title?: Text | null
  description?: Text | null
}

export interface CompanyTreatment {
  id: number
  slug: string
  title?: Text | null
  description?: Text | null
  image_url?: string | null
  badge?: string | null
  badge_label?: string | null
  category_tags: string[]
  detail_url?: string | null
}

export interface CompanyPromo {
  id: number
  title?: Text | null
  description?: RichText | null
  image_url?: string | null
  cta_label?: Text | null
  cta_url?: string | null
  cta_type?: string | null
}

export interface CompanyBrand {
  id: number
  name?: Text | null
  description?: Text | null
  logo_url?: string | null
  external_url?: string | null
}

export interface CompanyTestimonial {
  id: number
  quote?: RichText | null
  author_name: string
  since_year?: number | null
  avatar_url?: string | null
}

export interface CompanyContentSection {
  id: number
  section_key: string
  title?: Text | null
  body?: RichText | null
  image_url?: string | null
  cta_label?: Text | null
  cta_url?: string | null
  cta_type?: string | null
  layout_type: string
}

export interface CompanyLanding {
  is_published: boolean
  settings: CompanySettings | null
  navigation: { header: CompanyNavItem[]; footer: CompanyNavItem[] }
  slides: CompanySlide[]
  value_props: CompanyValueProp[]
  treatments: CompanyTreatment[]
  promos: CompanyPromo[]
  brands: CompanyBrand[]
  testimonials: CompanyTestimonial[]
  content_sections: Record<string, CompanyContentSection>
}

/**
 * Seluruh isi landing dalam satu permintaan — halaman merender 12 section
 * sekaligus, memecahnya jadi belasan request bikin kedip saat memuat.
 *
 * Konten datang sebagai peta bahasa; komponen yang memilih versinya lewat
 * `pickTranslatable`, sehingga ganti bahasa tidak perlu request ulang.
 */
export function useCompanyProfile(tenant: string, locale: ContentLocale) {
  return useQuery({
    queryKey: ["company-profile", tenant],
    queryFn: () =>
      apiGet<{ data: CompanyLanding }>(`/${tenant}/profile`, { locale }),
    select: (res) => res.data,
  })
}

export function useCompanyTreatment(
  tenant: string,
  slug: string,
  locale: ContentLocale,
) {
  return useQuery({
    queryKey: ["company-treatment", tenant, slug],
    queryFn: () =>
      apiGet<{ data: CompanyTreatment }>(
        `/${tenant}/profile/treatments/${slug}`,
        { locale },
      ),
    select: (res) => res.data,
  })
}

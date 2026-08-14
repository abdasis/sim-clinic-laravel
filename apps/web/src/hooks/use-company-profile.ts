import { useQuery } from "@tanstack/react-query"

import { apiGet } from "#/lib/api.ts"
import type { ContentLocale } from "#/lib/company-locale.ts"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"

export interface CompanySettings {
  is_published: boolean
  brand_name?: string | null
  tagline?: string | null
  logo_url?: string | null
  phone?: string | null
  whatsapp?: string | null
  email?: string | null
  address?: string | null
  map_embed_url?: string | null
  social_links?: Record<string, string>
  meta_title?: string | null
  meta_description?: string | null
  chat_widget_enabled: boolean
  chat_widget_number?: string | null
}

export interface CompanyNavItem {
  id: number
  label?: string | null
  position: string
  link_type: string
  link_value: string
}

export interface CompanySlide {
  id: number
  title?: string | null
  subtitle?: string | null
  image_url?: string | null
  cta_label?: string | null
  cta_type?: string | null
  cta_value?: string | null
}

export interface CompanyValueProp {
  id: number
  icon?: string | null
  title?: string | null
  description?: RichTextDoc | null
}

export interface CompanyTreatment {
  id: number
  slug: string
  name?: string | null
  excerpt?: string | null
  description?: RichTextDoc | null
  image_url?: string | null
  badge?: string | null
  badge_label?: string | null
  price_label?: string | null
}

export interface CompanyPromo {
  id: number
  title?: string | null
  description?: RichTextDoc | null
  image_url?: string | null
  cta_label?: string | null
  cta_type?: string | null
  cta_value?: string | null
}

export interface CompanyBrand {
  id: number
  name: string
  logo_url?: string | null
  url?: string | null
}

export interface CompanyTestimonial {
  id: number
  author_name: string
  author_role?: string | null
  quote?: RichTextDoc | null
  avatar_url?: string | null
  rating?: number | null
}

export interface CompanyContentSection {
  id: number
  section_key: string
  layout: string
  title?: string | null
  body?: RichTextDoc | null
  image_url?: string | null
  cta_label?: string | null
  cta_type?: string | null
  cta_value?: string | null
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
 */
export function useCompanyProfile(tenant: string, locale: ContentLocale) {
  return useQuery({
    queryKey: ["company-profile", tenant, locale],
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
    queryKey: ["company-treatment", tenant, slug, locale],
    queryFn: () =>
      apiGet<{ data: CompanyTreatment }>(
        `/${tenant}/profile/treatments/${slug}`,
        { locale },
      ),
    select: (res) => res.data,
  })
}

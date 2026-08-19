/**
 * Bentuk form tiap entitas konten. Cerminan `CompanyContentRegistry` di
 * backend — delapan entitas dilayani satu halaman daftar dan satu halaman
 * form, jadi perbedaannya cukup dijelaskan sebagai data.
 */
export type FieldType =
  | "text"
  | "number"
  | "url"
  | "translatable"
  | "rich"
  | "image"
  | "select"
  | "boolean"
  | "tags"

export interface FieldSchema {
  name: string
  labelKey: string
  type: FieldType
  required?: boolean
  options?: { value: string; labelKey: string }[]
  /** Kolom yang tampil di tabel daftar. */
  column?: boolean
}

export interface EntitySchema {
  /** Segmen URL, sama dengan kunci registry backend. */
  slug: string
  titleKey: string
  fields: FieldSchema[]
}

const CTA_TYPES = [
  { value: "route_internal", labelKey: "company_profile.cta_types.route_internal" },
  { value: "external", labelKey: "company_profile.cta_types.external" },
  { value: "whatsapp", labelKey: "company_profile.cta_types.whatsapp" },
]

const CTA_FIELDS: FieldSchema[] = [
  { name: "cta_label", labelKey: "company_profile.cta_label", type: "translatable" },
  { name: "cta_type", labelKey: "company_profile.cta_type", type: "select", options: CTA_TYPES },
  { name: "cta_url", labelKey: "company_profile.cta_value", type: "text" },
]

const ORDERING_FIELDS: FieldSchema[] = [
  { name: "sort_order", labelKey: "company_profile.sort_order", type: "number" },
  { name: "is_active", labelKey: "company_profile.is_active", type: "boolean" },
]

export const ENTITY_SCHEMAS: Record<string, EntitySchema> = {
  slides: {
    slug: "slides",
    titleKey: "company_profile.slides",
    fields: [
      { name: "title", labelKey: "company_profile.name", type: "translatable", required: true, column: true },
      { name: "subtitle", labelKey: "company_profile.excerpt", type: "translatable" },
      { name: "image_path", labelKey: "company_profile.image", type: "image" },
      ...CTA_FIELDS,
      ...ORDERING_FIELDS,
    ],
  },
  "value-props": {
    slug: "value-props",
    titleKey: "company_profile.value_props",
    fields: [
      { name: "title", labelKey: "company_profile.name", type: "translatable", required: true, column: true },
      { name: "description", labelKey: "company_profile.description", type: "translatable" },
      { name: "icon", labelKey: "company_profile.icon", type: "text" },
      ...ORDERING_FIELDS,
    ],
  },
  treatments: {
    slug: "treatments",
    titleKey: "company_profile.treatments",
    fields: [
      { name: "title", labelKey: "company_profile.name", type: "translatable", required: true, column: true },
      { name: "slug", labelKey: "company_profile.slug", type: "text", required: true, column: true },
      { name: "description", labelKey: "company_profile.excerpt", type: "translatable" },
      { name: "image_path", labelKey: "company_profile.image", type: "image" },
      {
        name: "badge",
        labelKey: "company_profile.badge",
        type: "select",
        options: [
          { value: "featured", labelKey: "company_profile.treatment_badges.featured" },
          { value: "current", labelKey: "company_profile.treatment_badges.current" },
        ],
      },
      { name: "category_tags", labelKey: "company_profile.excerpt", type: "tags" },
      { name: "detail_url", labelKey: "company_profile.url", type: "text" },
      ...ORDERING_FIELDS,
    ],
  },
  promos: {
    slug: "promos",
    titleKey: "company_profile.promos",
    fields: [
      { name: "title", labelKey: "company_profile.name", type: "translatable", required: true, column: true },
      { name: "description", labelKey: "company_profile.description", type: "rich" },
      { name: "image_path", labelKey: "company_profile.image", type: "image" },
      ...CTA_FIELDS,
      ...ORDERING_FIELDS,
    ],
  },
  testimonials: {
    slug: "testimonials",
    titleKey: "company_profile.testimonials",
    fields: [
      { name: "author_name", labelKey: "company_profile.author_name", type: "text", required: true, column: true },
      { name: "quote", labelKey: "company_profile.quote", type: "rich", required: true },
      { name: "since_year", labelKey: "company_profile.rating", type: "number" },
      { name: "avatar_path", labelKey: "company_profile.avatar", type: "image" },
      ...ORDERING_FIELDS,
    ],
  },
  "content-sections": {
    slug: "content-sections",
    titleKey: "company_profile.content_sections",
    fields: [
      {
        name: "section_key",
        labelKey: "company_profile.section_key",
        type: "select",
        required: true,
        column: true,
        options: [
          { value: "pharma_banner", labelKey: "company_profile.section_keys.pharma_banner" },
          { value: "booking_cta", labelKey: "company_profile.section_keys.booking_cta" },
          { value: "estore_cta", labelKey: "company_profile.section_keys.estore_cta" },
        ],
      },
      { name: "title", labelKey: "company_profile.name", type: "translatable" },
      { name: "body", labelKey: "company_profile.body", type: "rich" },
      { name: "image_path", labelKey: "company_profile.image", type: "image" },
      ...CTA_FIELDS,
      {
        name: "layout_type",
        labelKey: "company_profile.layout",
        type: "select",
        options: [
          { value: "banner", labelKey: "company_profile.section_layouts.banner" },
          { value: "split", labelKey: "company_profile.section_layouts.split" },
        ],
      },
      { name: "is_active", labelKey: "company_profile.is_active", type: "boolean" },
    ],
  },
  "navigation-items": {
    slug: "navigation-items",
    titleKey: "company_profile.navigation",
    fields: [
      { name: "label", labelKey: "company_profile.label", type: "translatable", required: true, column: true },
      {
        name: "position",
        labelKey: "company_profile.position",
        type: "select",
        required: true,
        column: true,
        options: [
          { value: "header", labelKey: "company_profile.nav_positions.header" },
          { value: "footer", labelKey: "company_profile.nav_positions.footer" },
        ],
      },
      {
        name: "link_type",
        labelKey: "company_profile.link_type",
        type: "select",
        required: true,
        options: [
          { value: "anchor_section", labelKey: "company_profile.link_types.anchor_section" },
          { value: "route_internal", labelKey: "company_profile.link_types.route_internal" },
          { value: "external", labelKey: "company_profile.link_types.external" },
        ],
      },
      { name: "url", labelKey: "company_profile.link_value", type: "text", required: true },
      { name: "is_cta", labelKey: "company_profile.cta_label", type: "boolean" },
      ...ORDERING_FIELDS,
    ],
  },
}

export const ENTITY_ORDER = Object.keys(ENTITY_SCHEMAS)

export function getEntitySchema(slug: string): EntitySchema | undefined {
  return ENTITY_SCHEMAS[slug]
}

/** Nilai awal form untuk entitas tertentu. */
export function emptyValues(schema: EntitySchema): Record<string, unknown> {
  const values: Record<string, unknown> = {}

  for (const field of schema.fields) {
    values[field.name] =
      field.type === "boolean"
        ? field.name === "is_active"
        : field.type === "number"
          ? 0
          : field.type === "translatable" || field.type === "rich"
            ? {}
            : field.type === "tags"
              ? []
              : ""
  }

  return values
}

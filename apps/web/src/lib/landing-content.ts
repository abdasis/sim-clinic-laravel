import type {
  CompanyBrand,
  CompanyContentSection,
  CompanyLanding,
  CompanyNavItem,
  CompanyPromo,
  CompanySlide,
  CompanyTestimonial,
  CompanyTreatment,
  CompanyValueProp,
} from "#/hooks/use-company-profile.ts"
import type { RichTextDoc } from "#/lib/tiptap-render.tsx"
import type { Translatable } from "#/lib/company-locale.ts"

/**
 * Isi landing marketing di akar situs. Sengaja statis: halaman ini menjual
 * produknya, bukan menampilkan data satu klinik — jadi tidak menunggu API,
 * tidak bisa kosong, dan tetap tampil walau backend mati.
 *
 * Bentuknya sengaja mengikuti `CompanyLanding` supaya seluruh komponen
 * section bisa dipakai apa adanya, sama seperti di halaman tenant.
 */

const WHATSAPP = "https://wa.me/6282273097656"

export const GOOGLE_MAPS_URL =
  "https://www.google.com/maps/place/Meba+Clinic+Aesthetic/@3.5530874,98.626229,17z"

/** Bungkus teks dua bahasa jadi dokumen Tiptap satu paragraf. */
function richText(pair: Translatable<string>): Translatable<RichTextDoc> {
  const wrap = (text: string): RichTextDoc => ({
    type: "doc",
    content: [{ type: "paragraph", content: [{ type: "text", text }] }],
  })

  return {
    id: pair.id ? wrap(pair.id) : undefined,
    en: pair.en ? wrap(pair.en) : undefined,
  }
}

/** Foto Unsplash; ukurannya dibatasi agar tidak menarik berkas mentah. */
function photo(id: string, width = 1200): string {
  return `https://images.unsplash.com/photo-${id}?auto=format&fit=crop&w=${width}&q=80`
}

const SLIDES: CompanySlide[] = [
  {
    id: 1,
    title: {
      id: "Kulit terbaikmu dimulai dari sini",
      en: "Your best skin starts here",
    },
    subtitle: {
      id: "Perawatan wajah, laser, dan hair removal oleh dokter bersertifikasi — dengan teknologi yang terbukti dan jadwal yang tepat waktu.",
      en: "Facial care, laser, and hair removal by certified doctors — proven technology, and appointments that start on time.",
    },
    image_url: photo("1570172619644-dfd03ed5d881", 1600),
    cta_label: { id: "Booking Sekarang", en: "Book Now" },
    cta_url: "/online-booking",
    cta_type: "route_root",
  },
  {
    id: 2,
    title: {
      id: "Klinik estetika di Medan",
      en: "Aesthetic clinic in Medan",
    },
    subtitle: {
      id: "Berlokasi di Ring Road Medan Selayang — mudah dijangkau, dengan parkir yang cukup dan ruang tunggu yang nyaman.",
      en: "Located on Ring Road, Medan Selayang — easy to reach, with ample parking and a comfortable waiting area.",
    },
    image_url: photo("1600334089648-b0d9d3028eb2", 1600),
    cta_label: { id: "Lihat Lokasi", en: "View Location" },
    cta_url: GOOGLE_MAPS_URL,
    cta_type: "external",
  },
  {
    id: 3,
    title: {
      id: "Konsultasi pertama, gratis",
      en: "Your first consultation is free",
    },
    subtitle: {
      id: "Dokter kami memetakan kondisi kulitmu dulu sebelum menyarankan treatment apa pun.",
      en: "Our doctors map your skin condition first, before recommending any treatment at all.",
    },
    image_url: photo("1596178060671-7a80dc8059ea", 1600),
    cta_label: { id: "Chat Dokter", en: "Chat a Doctor" },
    cta_url: WHATSAPP,
    cta_type: "whatsapp",
  },
]

const VALUE_PROPS: CompanyValueProp[] = [
  {
    id: 1,
    icon: "award",
    title: { id: "Berpengalaman", en: "Experienced" },
    description: {
      id: "Lebih dari 15 tahun menangani perawatan wajah, rambut, dan tubuh di Indonesia.",
      en: "More than 15 years handling face, hair, and body care across Indonesia.",
    },
  },
  {
    id: 2,
    icon: "shield-check",
    title: { id: "Dokter Bersertifikasi", en: "Certified Doctors" },
    description: {
      id: "Setiap tindakan ditangani dokter yang memprioritaskan keamanan sebelum hasil.",
      en: "Every procedure is handled by doctors who put safety before results.",
    },
  },
  {
    id: 3,
    icon: "cpu",
    title: { id: "Teknologi Terkini", en: "Latest Technology" },
    description: {
      id: "Perangkat mutakhir yang rutin dikalibrasi, dengan standar medis yang ketat.",
      en: "Up-to-date devices, calibrated regularly, under strict medical standards.",
    },
  },
  {
    id: 4,
    icon: "clock",
    title: { id: "Tepat Waktu", en: "On Time" },
    description: {
      id: "Waktumu berharga. Jadwal yang kami janjikan adalah jadwal yang kami tepati.",
      en: "Your time matters. The schedule we promise is the schedule we keep.",
    },
  },
  {
    id: 5,
    icon: "map",
    title: { id: "Lokasi Strategis", en: "Strategic Location" },
    description: {
      id: "Berlokasi di Ring Road Medan Selayang, mudah dijangkau dari berbagai arah.",
      en: "Located on Ring Road, Medan Selayang — easy to reach from any direction.",
    },
  },
  {
    id: 6,
    icon: "smile",
    title: { id: "Pelayanan Ramah", en: "Friendly Service" },
    description: {
      id: "Staf kami mengutamakan kenyamanan, dari ruang tunggu sampai ruang tindakan.",
      en: "Our staff put your comfort first, from the waiting room to the treatment room.",
    },
  },
]

const TREATMENTS: CompanyTreatment[] = [
  {
    id: 1,
    slug: "facial-glow",
    title: { id: "Facial Glow", en: "Facial Glow" },
    description: {
      id: "Membersihkan, mengangkat sel kulit mati, dan mencerahkan wajah dalam 60 menit.",
      en: "Cleanses, lifts dead skin cells, and brightens your face in 60 minutes.",
    },
    image_url: photo("1570172619644-dfd03ed5d881"),
    badge: "featured",
    badge_label: "Terfavorit",
    category_tags: ["Facial", "Rejuvenation"],
    detail_url: null,
  },
  {
    id: 2,
    slug: "hair-removal",
    title: { id: "Permanent Hair Removal", en: "Permanent Hair Removal" },
    description: {
      id: "Laser yang menargetkan akar rambut secara bertahap, aman untuk kulit sensitif.",
      en: "Laser that targets hair roots gradually, safe even for sensitive skin.",
    },
    image_url: photo("1519824145371-296894a0daa9"),
    badge: "current",
    badge_label: "Sedang Berjalan",
    category_tags: ["Menghilangkan Bulu", "Laser"],
    detail_url: null,
  },
  {
    id: 3,
    slug: "chemical-peeling",
    title: { id: "Chemical Peeling", en: "Chemical Peeling" },
    description: {
      id: "Meratakan tekstur kulit dan menyamarkan bekas jerawat tanpa waktu pulih panjang.",
      en: "Evens skin texture and fades acne scars without a long recovery.",
    },
    image_url: photo("1512290923902-8a9f81dc236c"),
    badge: null,
    badge_label: null,
    category_tags: ["Acne", "Brightening"],
    detail_url: null,
  },
  {
    id: 4,
    slug: "laser-rejuvenation",
    title: { id: "Laser Rejuvenation", en: "Laser Rejuvenation" },
    description: {
      id: "Merangsang kolagen untuk mengencangkan kulit, dengan waktu pemulihan singkat.",
      en: "Stimulates collagen to firm the skin, with short downtime.",
    },
    image_url: photo("1583241800698-9c2e0b1b1b5c"),
    badge: "featured",
    badge_label: "Terfavorit",
    category_tags: ["Laser", "Anti Aging"],
    detail_url: null,
  },
  {
    id: 5,
    slug: "acne-treatment",
    title: { id: "Acne Treatment", en: "Acne Treatment" },
    description: {
      id: "Penanganan jerawat aktif beserta rencana perawatan lanjutan di rumah.",
      en: "Care for active acne, plus a follow-up routine you can keep at home.",
    },
    image_url: photo("1573461160327-b450ce3d8e7f"),
    badge: null,
    badge_label: null,
    category_tags: ["Acne"],
    detail_url: null,
  },
  {
    id: 6,
    slug: "body-slimming",
    title: { id: "Body Contouring", en: "Body Contouring" },
    description: {
      id: "Membentuk area tubuh tertentu tanpa sayatan, bertahap dan terukur.",
      en: "Shapes specific body areas without incisions — gradual and measured.",
    },
    image_url: photo("1571019613454-1cb2f99b2d8b"),
    badge: null,
    badge_label: null,
    category_tags: ["Body", "Non Invasive"],
    detail_url: null,
  },
]

const PROMOS: CompanyPromo[] = [
  {
    id: 1,
    title: { id: "Paket Berdua Hemat 20%", en: "Bring a Friend, Save 20%" },
    description: richText({
      id: "Ajak teman untuk treatment yang sama dan dapatkan potongan 20% untuk kalian berdua.",
      en: "Book the same treatment with a friend and you both get 20% off.",
    }),
    image_url: photo("1600334089648-b0d9d3028eb2", 800),
    cta_label: { id: "Ambil Promo", en: "Claim Promo" },
    cta_url: WHATSAPP,
    cta_type: "whatsapp",
  },
  {
    id: 2,
    title: { id: "Member Baru: Facial Gratis", en: "New Member: Free Facial" },
    description: richText({
      id: "Daftar member bulan ini dan dapatkan satu sesi facial dasar tanpa biaya.",
      en: "Sign up as a member this month and get one basic facial session on us.",
    }),
    image_url: photo("1596178060671-7a80dc8059ea", 800),
    cta_label: { id: "Lihat Syarat", en: "See Terms" },
    cta_url: "/promo",
    cta_type: "route_root",
  },
]

const BRANDS: CompanyBrand[] = [
  {
    id: 1,
    name: { id: "Klinik Utama", en: "Flagship Clinic" },
    description: {
      id: "Layanan lengkap perawatan wajah, laser, dan tubuh dengan dokter jaga setiap hari.",
      en: "Full-service face, laser, and body care with doctors on duty every day.",
    },
    logo_url: null,
    external_url: null,
  },
  {
    id: 2,
    name: { id: "Express", en: "Express" },
    description: {
      id: "Gerai cepat untuk perawatan rutin di pusat perbelanjaan, tanpa perlu janji.",
      en: "Quick-service outlets in malls for routine care, no appointment needed.",
    },
    logo_url: null,
    external_url: null,
  },
  {
    id: 3,
    name: { id: "Apotek & E-Store", en: "Pharmacy & E-Store" },
    description: {
      id: "Produk perawatan resmi yang diresepkan dokter kami, bisa dikirim ke rumah.",
      en: "Doctor-prescribed skincare, delivered to your door.",
    },
    logo_url: null,
    external_url: null,
  },
]

const TESTIMONIALS: CompanyTestimonial[] = [
  {
    id: 1,
    quote: richText({
      id: "Kulit terasa jauh lebih halus setelah tiga kali datang. Dokternya sabar menjelaskan, tidak terburu-buru menawarkan paket.",
      en: "My skin felt much smoother after three visits. The doctor explained things patiently and never rushed to upsell a package.",
    }),
    author_name: "Nadia P.",
    since_year: 2022,
    avatar_url: null,
  },
  {
    id: 2,
    quote: richText({
      id: "Prosesnya cepat dan tidak semenakutkan yang saya bayangkan. Sekarang jadi rutin tiap bulan.",
      en: "The procedure was quick and far less scary than I imagined. Now it is a monthly routine.",
    }),
    author_name: "Rangga S.",
    since_year: 2024,
    avatar_url: null,
  },
  {
    id: 3,
    quote: richText({
      id: "Yang bikin betah: jadwalnya benar-benar tepat waktu. Sekali pun belum pernah menunggu lama.",
      en: "What keeps me coming back: appointments actually start on time. I have never had a long wait.",
    }),
    author_name: "Levina A.",
    since_year: 2021,
    avatar_url: null,
  },
]

const CONTENT_SECTIONS: Record<string, CompanyContentSection> = {
  pharma_banner: {
    id: 1,
    section_key: "pharma_banner",
    title: { id: "Apotek Klinik", en: "Clinic Pharmacy" },
    body: richText({
      id: "Produk perawatan resmi yang diresepkan dokter kami, tersedia langsung di klinik maupun dikirim ke rumah.",
      en: "Doctor-prescribed skincare, available at the clinic or delivered to your home.",
    }),
    image_url: photo("1556228720-195a672e8a03", 900),
    cta_label: { id: "Lihat Produk", en: "See Products" },
    cta_url: "/store",
    cta_type: "route_root",
    layout_type: "split",
  },
  booking_cta: {
    id: 2,
    section_key: "booking_cta",
    title: {
      id: "Siap memulai perawatan?",
      en: "Ready to start your treatment?",
    },
    body: richText({
      id: "Pilih jadwal yang pas, kami konfirmasi lewat WhatsApp dalam hitungan menit.",
      en: "Pick a time that suits you; we confirm over WhatsApp within minutes.",
    }),
    image_url: null,
    cta_label: { id: "Booking Sekarang", en: "Book Now" },
    cta_url: "/online-booking",
    cta_type: "route_root",
    layout_type: "banner",
  },
  estore_cta: {
    id: 3,
    section_key: "estore_cta",
    title: { id: "Belanja dari rumah", en: "Shop from home" },
    body: richText({
      id: "Produk yang sama dengan yang dipakai di klinik, dikirim ke alamatmu.",
      en: "The same products we use in the clinic, delivered to your address.",
    }),
    image_url: null,
    cta_label: { id: "Kunjungi Toko", en: "Visit Store" },
    cta_url: "/store",
    cta_type: "route_root",
    layout_type: "banner",
  },
}

const HEADER_NAV: CompanyNavItem[] = [
  {
    id: 1,
    label: { id: "Treatment", en: "Treatments" },
    url: "/treatments",
    link_type: "route_root",
    position: "header",
    is_cta: false,
  },
  {
    id: 2,
    label: { id: "Promo", en: "Promos" },
    url: "/promo",
    link_type: "route_root",
    position: "header",
    is_cta: false,
  },
  {
    id: 3,
    label: { id: "E-Store", en: "E-Store" },
    url: "/store",
    link_type: "route_root",
    position: "header",
    is_cta: false,
  },
  {
    id: 4,
    label: { id: "Lokasi", en: "Locations" },
    url: "/locations",
    link_type: "route_root",
    position: "header",
    is_cta: false,
  },
  {
    id: 5,
    label: { id: "Artikel", en: "Articles" },
    url: "/artikel",
    link_type: "route_root",
    position: "header",
    is_cta: false,
  },
  {
    id: 6,
    label: { id: "Online Booking", en: "Online Booking" },
    url: "/online-booking",
    link_type: "route_root",
    position: "header",
    is_cta: true,
  },
]

const FOOTER_NAV: CompanyNavItem[] = [
  {
    id: 7,
    label: { id: "Liputan", en: "Press" },
    url: "/artikel",
    link_type: "route_root",
    position: "footer",
    is_cta: false,
  },
  {
    id: 8,
    label: { id: "FAQ", en: "FAQ" },
    url: "/artikel",
    link_type: "route_root",
    position: "footer",
    is_cta: false,
  },
  {
    id: 9,
    label: { id: "Kontak Kami", en: "Contact Us" },
    url: WHATSAPP,
    link_type: "external",
    position: "footer",
    is_cta: false,
  },
]

export const LANDING_CONTENT: CompanyLanding = {
  is_published: true,
  settings: {
    site_name: { id: "Meba Clinic Aesthetic", en: "Meba Clinic Aesthetic" },
    logo_path: null,
    logo_url: null,
    copyright_text: "Copyright © 2026 Meba Clinic Aesthetic. All Right Reserved",
    chat_channels: [
      {
        type: "whatsapp",
        url: WHATSAPP,
        label: { id: "Chat WhatsApp", en: "Chat on WhatsApp" },
      },
    ],
    social_links: [
      { platform: "instagram", url: "https://instagram.com/", icon: "instagram" },
      { platform: "facebook", url: "https://facebook.com/", icon: "facebook" },
      { platform: "tiktok", url: "https://tiktok.com/", icon: "tiktok" },
    ],
    // Klinik menjual lewat kliniknya sendiri, bukan lewat lapak marketplace.
    marketplace_links: [],
    default_locale: "id",
    is_published: true,
  },
  faqs: [],
  navigation: { header: HEADER_NAV, footer: FOOTER_NAV },
  slides: SLIDES,
  value_props: VALUE_PROPS,
  treatments: TREATMENTS,
  promos: PROMOS,
  brands: BRANDS,
  testimonials: TESTIMONIALS,
  content_sections: CONTENT_SECTIONS,
}

/** Produk e-store; hanya etalase, belum ada keranjang yang benar-benar jalan. */
export interface StoreProduct {
  id: number
  name: Translatable<string>
  description: Translatable<string>
  price: number
  image_url: string
}

export const STORE_PRODUCTS: StoreProduct[] = [
  {
    id: 1,
    name: { id: "Gentle Cleanser", en: "Gentle Cleanser" },
    description: {
      id: "Pembersih harian tanpa sabun untuk kulit sensitif.",
      en: "Soap-free daily cleanser for sensitive skin.",
    },
    price: 85000,
    image_url: photo("1556228720-195a672e8a03", 600),
  },
  {
    id: 2,
    name: { id: "Brightening Serum", en: "Brightening Serum" },
    description: {
      id: "Serum vitamin C untuk meratakan warna kulit.",
      en: "Vitamin C serum that evens out skin tone.",
    },
    price: 245000,
    image_url: photo("1620916566398-39f1143ab7be", 600),
  },
  {
    id: 3,
    name: { id: "Barrier Moisturizer", en: "Barrier Moisturizer" },
    description: {
      id: "Pelembab ringan yang memperbaiki lapisan pelindung kulit.",
      en: "Lightweight moisturizer that repairs the skin barrier.",
    },
    price: 175000,
    image_url: photo("1570194065650-d99fb4bedf0a", 600),
  },
  {
    id: 4,
    name: { id: "Sunscreen SPF 50", en: "Sunscreen SPF 50" },
    description: {
      id: "Tabir surya harian yang tidak meninggalkan lapisan putih.",
      en: "Daily sunscreen that leaves no white cast.",
    },
    price: 145000,
    image_url: photo("1556229174-5e42a09e45af", 600),
  },
  {
    id: 5,
    name: { id: "Acne Spot Gel", en: "Acne Spot Gel" },
    description: {
      id: "Gel setempat untuk meredakan jerawat aktif.",
      en: "Spot gel that calms active breakouts.",
    },
    price: 95000,
    image_url: photo("1608248543803-ba4f8c70ae0b", 600),
  },
  {
    id: 6,
    name: { id: "Retinol Night Cream", en: "Retinol Night Cream" },
    description: {
      id: "Krim malam berretinol dosis rendah untuk pemula.",
      en: "Low-dose retinol night cream for beginners.",
    },
    price: 265000,
    image_url: photo("1601049541289-9b1b7bbbfe19", 600),
  },
]


/** Artikel; ringkasan statis, belum ada halaman detailnya. */
export interface Article {
  id: number
  title: Translatable<string>
  excerpt: Translatable<string>
  category: Translatable<string>
  published_at: string
  image_url: string
}

export const ARTICLES: Article[] = [
  {
    id: 1,
    title: {
      id: "Urutan skincare yang benar untuk pemula",
      en: "The right skincare order for beginners",
    },
    excerpt: {
      id: "Terlalu banyak produk justru membebani kulit. Mulai dari tiga langkah ini.",
      en: "Too many products only burden your skin. Start with these three steps.",
    },
    category: { id: "Panduan", en: "Guide" },
    published_at: "2026-07-02",
    image_url: photo("1556228720-195a672e8a03", 800),
  },
  {
    id: 2,
    title: {
      id: "Apa yang terjadi saat laser hair removal?",
      en: "What actually happens during laser hair removal?",
    },
    excerpt: {
      id: "Penjelasan tahap demi tahap, termasuk rasa yang wajar dan yang tidak.",
      en: "A step-by-step explanation, including what discomfort is normal and what is not.",
    },
    category: { id: "Treatment", en: "Treatment" },
    published_at: "2026-06-18",
    image_url: photo("1519824145371-296894a0daa9", 800),
  },
  {
    id: 3,
    title: {
      id: "Kenapa jerawat kembali setelah sembuh?",
      en: "Why acne comes back after it clears",
    },
    excerpt: {
      id: "Faktor pemicu yang paling sering terlewat, dan cara membaca polanya.",
      en: "The triggers people miss most often, and how to read your own pattern.",
    },
    category: { id: "Kulit", en: "Skin" },
    published_at: "2026-05-30",
    image_url: photo("1573461160327-b450ce3d8e7f", 800),
  },
]

/** Cari treatment berdasarkan slug untuk halaman detail. */
export function findTreatment(slug: string): CompanyTreatment | undefined {
  return TREATMENTS.find((treatment) => treatment.slug === slug)
}

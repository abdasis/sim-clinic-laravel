<?php

namespace Database\Seeders;

use App\Enums\CompanyCtaType;
use App\Enums\CompanyLinkType;
use App\Enums\CompanyNavPosition;
use App\Enums\CompanySectionKey;
use App\Enums\CompanySectionLayout;
use App\Enums\CompanyTreatmentBadge;
use App\Models\CompanyBrand;
use App\Models\CompanyContentSection;
use App\Models\CompanyNavigationItem;
use App\Models\CompanyProfileSetting;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyPromo;
use App\Models\CompanyTestimonial;
use App\Models\CompanyTreatment;
use App\Models\CompanyValueProp;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Isi contoh landing publik untuk tenant demo (spec 010) supaya halaman
 * bisa dibuka dan dinilai tanpa harus mengisi CMS dari nol.
 */
class CompanyProfileDemoSeeder extends Seeder
{
    private const WHATSAPP = 'https://wa.me/6281234567890';

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'demo')->first();

        if (! $tenant) {
            $this->command?->warn('Tenant demo belum ada; lewati seeder company profile.');

            return;
        }

        app()->instance('tenant', $tenant);

        $this->seedSettings($tenant->id);
        $this->seedNavigation($tenant->id);
        $this->seedSlides($tenant->id);
        $this->seedValueProps($tenant->id);
        $this->seedTreatments($tenant->id);
        $this->seedPromos($tenant->id);
        $this->seedBrands($tenant->id);
        $this->seedTestimonials($tenant->id);
        $this->seedContentSections($tenant->id);
    }

    private function seedSettings(int $tenantId): void
    {
        CompanyProfileSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'site_name' => ['id' => 'Klinik Cantik Demo', 'en' => 'Demo Beauty Clinic'],
                'copyright_text' => 'Klinik Cantik Demo',
                'chat_channels' => [[
                    'type' => 'whatsapp',
                    'url' => self::WHATSAPP,
                    'label' => ['id' => 'Chat WhatsApp', 'en' => 'Chat on WhatsApp'],
                ]],
                'social_links' => [
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/klinikcantikdemo', 'icon' => 'instagram'],
                    ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@klinikcantikdemo', 'icon' => 'tiktok'],
                ],
                'marketplace_links' => [
                    ['name' => 'Tokopedia', 'url' => 'https://tokopedia.com/klinikcantikdemo', 'icon' => 'shopping-bag'],
                ],
                'default_locale' => 'id',
                'is_published' => true,
            ],
        );
    }

    private function seedNavigation(int $tenantId): void
    {
        $items = [
            ['#keunggulan', ['id' => 'Keunggulan', 'en' => 'Highlights'], CompanyNavPosition::Header, 1, false],
            ['#treatment', ['id' => 'Treatment', 'en' => 'Treatments'], CompanyNavPosition::Header, 2, false],
            ['#promo', ['id' => 'Promo', 'en' => 'Promos'], CompanyNavPosition::Header, 3, false],
            ['#booking', ['id' => 'Online Booking', 'en' => 'Online Booking'], CompanyNavPosition::Header, 4, true],
            ['#testimoni', ['id' => 'Testimoni', 'en' => 'Testimonials'], CompanyNavPosition::Footer, 1, false],
            ['#estore', ['id' => 'Belanja Produk', 'en' => 'Shop Products'], CompanyNavPosition::Footer, 2, false],
        ];

        foreach ($items as [$url, $label, $position, $order, $isCta]) {
            CompanyNavigationItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'url' => $url, 'position' => $position],
                [
                    'label' => $label,
                    'link_type' => CompanyLinkType::AnchorSection,
                    'sort_order' => $order,
                    'is_active' => true,
                    'is_cta' => $isCta,
                ],
            );
        }
    }

    private function seedSlides(int $tenantId): void
    {
        $slides = [
            [
                ['id' => 'Kulit sehat dimulai dari perawatan yang tepat', 'en' => 'Healthy skin starts with the right care'],
                ['id' => 'Konsultasi bersama dokter kami, gratis untuk kunjungan pertama.', 'en' => 'Consult our doctors, free on your first visit.'],
                1,
            ],
            [
                ['id' => 'Teknologi laser generasi terbaru', 'en' => 'Latest generation laser technology'],
                ['id' => 'Aman, cepat, dan minim waktu pemulihan.', 'en' => 'Safe, quick, and minimal downtime.'],
                2,
            ],
        ];

        foreach ($slides as [$title, $subtitle, $order]) {
            CompanyProfileSlide::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'sort_order' => $order],
                [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'image_path' => 'company-profile/demo/hero-'.$order.'.jpg',
                    'cta_label' => ['id' => 'Booking Sekarang', 'en' => 'Book Now'],
                    'cta_url' => self::WHATSAPP,
                    'cta_type' => CompanyCtaType::Whatsapp,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedValueProps(int $tenantId): void
    {
        $props = [
            ['shield-check', ['id' => 'Dokter Berpengalaman', 'en' => 'Experienced Doctors'], ['id' => 'Ditangani dokter bersertifikat dengan jam terbang tinggi.', 'en' => 'Handled by certified doctors with years of practice.']],
            ['sparkles', ['id' => 'Alat Modern', 'en' => 'Modern Equipment'], ['id' => 'Perangkat mutakhir yang rutin dikalibrasi.', 'en' => 'Up-to-date devices, calibrated regularly.']],
            ['heart', ['id' => 'Produk Original', 'en' => 'Original Products'], ['id' => 'Seluruh produk resmi dan terdaftar BPOM.', 'en' => 'All products are genuine and registered.']],
        ];

        foreach ($props as $index => [$icon, $title, $description]) {
            CompanyValueProp::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'sort_order' => $index + 1],
                [
                    'icon' => $icon,
                    'title' => $title,
                    'description' => $description,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedTreatments(int $tenantId): void
    {
        $treatments = [
            ['facial-glow', ['id' => 'Facial Glow', 'en' => 'Facial Glow'], ['id' => 'Membersihkan dan mencerahkan wajah dalam 60 menit.', 'en' => 'Cleanses and brightens your face in 60 minutes.'], CompanyTreatmentBadge::Featured, ['facial', 'rejuvenation']],
            ['chemical-peeling', ['id' => 'Chemical Peeling', 'en' => 'Chemical Peeling'], ['id' => 'Meratakan tekstur kulit dan menyamarkan bekas jerawat.', 'en' => 'Evens skin texture and fades acne scars.'], null, ['acne']],
            ['laser-rejuvenation', ['id' => 'Laser Rejuvenation', 'en' => 'Laser Rejuvenation'], ['id' => 'Meremajakan kulit dengan waktu pemulihan singkat.', 'en' => 'Rejuvenates skin with short downtime.'], CompanyTreatmentBadge::Current, ['laser', 'rejuvenation']],
        ];

        foreach ($treatments as $index => [$slug, $title, $description, $badge, $tags]) {
            CompanyTreatment::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                [
                    'title' => $title,
                    'description' => $description,
                    'image_path' => 'company-profile/demo/'.$slug.'.jpg',
                    'badge' => $badge,
                    'category_tags' => $tags,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedPromos(int $tenantId): void
    {
        CompanyPromo::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'sort_order' => 1],
            [
                'title' => ['id' => 'Paket Berdua Hemat 20%', 'en' => 'Bring a Friend, Save 20%'],
                'description' => $this->richTextPair([
                    'id' => 'Ajak teman untuk treatment yang sama dan dapatkan potongan 20% untuk keduanya.',
                    'en' => 'Book the same treatment with a friend and both get 20% off.',
                ]),
                'image_path' => 'company-profile/demo/promo-berdua.jpg',
                'cta_label' => ['id' => 'Ambil Promo', 'en' => 'Claim Promo'],
                'cta_url' => self::WHATSAPP,
                'cta_type' => CompanyCtaType::Whatsapp,
                'is_active' => true,
            ],
        );
    }

    private function seedBrands(int $tenantId): void
    {
        $brands = [
            ['Dermalogica', ['id' => 'Perawatan kulit profesional.', 'en' => 'Professional skincare.']],
            ['La Roche-Posay', ['id' => 'Direkomendasikan dermatolog.', 'en' => 'Dermatologist recommended.']],
            ['SkinCeuticals', ['id' => 'Formula berbasis riset.', 'en' => 'Research-backed formulas.']],
        ];

        foreach ($brands as $index => [$name, $description]) {
            CompanyBrand::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'sort_order' => $index + 1],
                [
                    'name' => ['id' => $name, 'en' => $name],
                    'description' => $description,
                    'logo_path' => 'company-profile/demo/brand-'.($index + 1).'.png',
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedTestimonials(int $tenantId): void
    {
        $testimonials = [
            ['Nadia P.', 2022, ['id' => 'Kulit terasa jauh lebih halus setelah tiga kali datang. Dokternya sabar menjelaskan.', 'en' => 'My skin feels much smoother after three visits. The doctor explains everything patiently.']],
            ['Rangga S.', 2024, ['id' => 'Prosesnya cepat dan tidak menakutkan seperti yang saya bayangkan.', 'en' => 'The procedure was quick and far less scary than I imagined.']],
        ];

        foreach ($testimonials as $index => [$author, $since, $quote]) {
            CompanyTestimonial::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'author_name' => $author],
                [
                    'quote' => $this->richTextPair($quote),
                    'since_year' => $since,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedContentSections(int $tenantId): void
    {
        $sections = [
            [
                CompanySectionKey::PharmaBanner,
                CompanySectionLayout::Split,
                ['id' => 'Apotek Klinik', 'en' => 'Clinic Pharmacy'],
                ['id' => 'Produk perawatan resmi yang diresepkan dokter kami, tersedia langsung di klinik.', 'en' => 'Doctor-prescribed skincare, available right at the clinic.'],
                ['id' => 'Lihat Produk', 'en' => 'See Products'],
                CompanyCtaType::External,
                'https://estore.klinikcantik.test',
            ],
            [
                CompanySectionKey::BookingCta,
                CompanySectionLayout::Banner,
                ['id' => 'Siap memulai perawatan?', 'en' => 'Ready to start your treatment?'],
                ['id' => 'Pilih jadwal yang pas, kami konfirmasi lewat WhatsApp.', 'en' => 'Pick a time that suits you; we confirm over WhatsApp.'],
                ['id' => 'Booking Sekarang', 'en' => 'Book Now'],
                CompanyCtaType::Whatsapp,
                self::WHATSAPP,
            ],
            [
                CompanySectionKey::EstoreCta,
                CompanySectionLayout::Banner,
                ['id' => 'Belanja dari rumah', 'en' => 'Shop from home'],
                ['id' => 'Produk yang sama, dikirim ke alamatmu.', 'en' => 'The same products, delivered to your door.'],
                ['id' => 'Kunjungi Toko', 'en' => 'Visit Store'],
                CompanyCtaType::External,
                'https://estore.klinikcantik.test',
            ],
        ];

        foreach ($sections as [$key, $layout, $title, $body, $ctaLabel, $ctaType, $ctaUrl]) {
            CompanyContentSection::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'section_key' => $key],
                [
                    'title' => $title,
                    'body' => $this->richTextPair($body),
                    'cta_label' => $ctaLabel,
                    'cta_url' => $ctaUrl,
                    'cta_type' => $ctaType,
                    'layout_type' => $layout,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Bungkus teks dua bahasa jadi dokumen Tiptap satu paragraf.
     *
     * @param  array<string, string>  $pair
     * @return array<string, mixed>
     */
    private function richTextPair(array $pair): array
    {
        return array_map(
            fn (string $text) => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $text]],
                ]],
            ],
            $pair,
        );
    }
}

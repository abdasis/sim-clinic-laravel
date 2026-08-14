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
                'is_published' => true,
                'brand_name' => ['id' => 'Klinik Cantik Demo', 'en' => 'Demo Beauty Clinic'],
                'tagline' => [
                    'id' => 'Rawat kulitmu, tenang dan terukur.',
                    'en' => 'Care for your skin, calmly and precisely.',
                ],
                'phone' => '021-5550123',
                'whatsapp' => '081234567890',
                'email' => 'halo@klinikcantik.test',
                'address' => [
                    'id' => 'Jl. Melati No. 12, Jakarta Selatan',
                    'en' => 'Jl. Melati No. 12, South Jakarta',
                ],
                'social_links' => [
                    'instagram' => 'https://instagram.com/klinikcantikdemo',
                    'tiktok' => 'https://tiktok.com/@klinikcantikdemo',
                ],
                'meta_title' => ['id' => 'Klinik Cantik Demo', 'en' => 'Demo Beauty Clinic'],
                'meta_description' => [
                    'id' => 'Perawatan wajah dan kulit oleh dokter berpengalaman.',
                    'en' => 'Face and skin treatments by experienced doctors.',
                ],
                'chat_widget_enabled' => true,
                'chat_widget_number' => '081234567890',
            ],
        );
    }

    private function seedNavigation(int $tenantId): void
    {
        $items = [
            ['#keunggulan', ['id' => 'Keunggulan', 'en' => 'Highlights'], CompanyNavPosition::Header, 1],
            ['#treatment', ['id' => 'Treatment', 'en' => 'Treatments'], CompanyNavPosition::Header, 2],
            ['#promo', ['id' => 'Promo', 'en' => 'Promos'], CompanyNavPosition::Header, 3],
            ['#testimoni', ['id' => 'Testimoni', 'en' => 'Testimonials'], CompanyNavPosition::Header, 4],
            ['#booking', ['id' => 'Booking', 'en' => 'Booking'], CompanyNavPosition::Footer, 1],
            ['#estore', ['id' => 'Belanja Produk', 'en' => 'Shop Products'], CompanyNavPosition::Footer, 2],
        ];

        foreach ($items as [$link, $label, $position, $order]) {
            CompanyNavigationItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'link_value' => $link, 'position' => $position],
                [
                    'label' => $label,
                    'link_type' => CompanyLinkType::AnchorSection,
                    'sort_order' => $order,
                    'is_active' => true,
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
                    'cta_type' => CompanyCtaType::Whatsapp,
                    'cta_value' => '081234567890',
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
                    'description' => $this->richTextPair($description),
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedTreatments(int $tenantId): void
    {
        $treatments = [
            ['facial-glow', ['id' => 'Facial Glow', 'en' => 'Facial Glow'], ['id' => 'Membersihkan dan mencerahkan wajah dalam 60 menit.', 'en' => 'Cleanses and brightens your face in 60 minutes.'], CompanyTreatmentBadge::Featured, 'Mulai 250rb'],
            ['chemical-peeling', ['id' => 'Chemical Peeling', 'en' => 'Chemical Peeling'], ['id' => 'Meratakan tekstur kulit dan menyamarkan bekas jerawat.', 'en' => 'Evens skin texture and fades acne scars.'], null, 'Mulai 400rb'],
            ['laser-rejuvenation', ['id' => 'Laser Rejuvenation', 'en' => 'Laser Rejuvenation'], ['id' => 'Meremajakan kulit dengan waktu pemulihan singkat.', 'en' => 'Rejuvenates skin with short downtime.'], CompanyTreatmentBadge::Current, 'Mulai 900rb'],
        ];

        foreach ($treatments as $index => [$slug, $name, $excerpt, $badge, $price]) {
            CompanyTreatment::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                [
                    'name' => $name,
                    'excerpt' => $excerpt,
                    'description' => $this->richTextPair($excerpt),
                    'image_path' => 'company-profile/demo/'.$slug.'.jpg',
                    'badge' => $badge,
                    'price_label' => $price,
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
                'cta_type' => CompanyCtaType::Whatsapp,
                'cta_value' => '081234567890',
                'is_active' => true,
            ],
        );
    }

    private function seedBrands(int $tenantId): void
    {
        foreach (['Dermalogica', 'La Roche-Posay', 'SkinCeuticals'] as $index => $name) {
            CompanyBrand::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                [
                    'logo_path' => 'company-profile/demo/brand-'.($index + 1).'.png',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedTestimonials(int $tenantId): void
    {
        $testimonials = [
            ['Nadia P.', ['id' => 'Pasien Facial', 'en' => 'Facial Patient'], ['id' => 'Kulit terasa jauh lebih halus setelah tiga kali datang. Dokternya sabar menjelaskan.', 'en' => 'My skin feels much smoother after three visits. The doctor explains everything patiently.']],
            ['Rangga S.', ['id' => 'Pasien Laser', 'en' => 'Laser Patient'], ['id' => 'Prosesnya cepat dan tidak menakutkan seperti yang saya bayangkan.', 'en' => 'The procedure was quick and far less scary than I imagined.']],
        ];

        foreach ($testimonials as $index => [$author, $role, $quote]) {
            CompanyTestimonial::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'author_name' => $author],
                [
                    'author_role' => $role,
                    'quote' => $this->richTextPair($quote),
                    'rating' => 5,
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
                '081234567890',
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

        foreach ($sections as [$key, $layout, $title, $body, $ctaLabel, $ctaType, $ctaValue]) {
            CompanyContentSection::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'section_key' => $key],
                [
                    'layout' => $layout,
                    'title' => $title,
                    'body' => $this->richTextPair($body),
                    'cta_label' => $ctaLabel,
                    'cta_type' => $ctaType,
                    'cta_value' => $ctaValue,
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

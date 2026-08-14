<?php

namespace Database\Seeders;

use App\Enums\CompanyCtaType;
use App\Enums\CompanyLinkType;
use App\Enums\CompanyNavPosition;
use App\Enums\CompanySectionKey;
use App\Enums\CompanySectionLayout;
use App\Enums\CompanyTreatmentBadge;
use App\Enums\TenantStatus;
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
// ponytail: konten dummy ZAP Clinic untuk demo landing, ganti via CMS saat produksi
class CompanyProfileDemoSeeder extends Seeder
{
    private const WHATSAPP = 'https://wa.me/6281234567890';

    public function run(): void
    {
        // Isi semua tenant klinik aktif (slug bukan 'central') supaya demo
        // landing bisa dibuka di tenant mana pun tanpa hardcode slug.
        $tenants = Tenant::query()
            ->where('slug', '!=', 'central')
            ->where('status', TenantStatus::Active)
            ->get();

        if ($tenants->isEmpty()) {
            $this->command?->warn('Tidak ada tenant klinik aktif; lewati seeder company profile.');

            return;
        }

        $tenants->each(function (Tenant $tenant): void {
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
        });
    }

    private function seedSettings(int $tenantId): void
    {
        CompanyProfileSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'site_name' => ['id' => 'ZAP Clinic', 'en' => 'ZAP Clinic'],
                'copyright_text' => 'ZAP Clinic',
                'chat_channels' => [[
                    'type' => 'whatsapp',
                    'url' => self::WHATSAPP,
                    'label' => ['id' => 'Chat WhatsApp', 'en' => 'Chat on WhatsApp'],
                ]],
                'social_links' => [
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/zapclinic', 'icon' => 'instagram'],
                    ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@zapclinic', 'icon' => 'tiktok'],
                ],
                'marketplace_links' => [
                    ['name' => 'Tokopedia', 'url' => 'https://tokopedia.com/zapclinic', 'icon' => 'shopping-bag'],
                ],
                'default_locale' => 'id',
                'is_published' => true,
            ],
        );
    }

    private function seedNavigation(int $tenantId): void
    {
        $items = [
            ['#keunggulan', ['id' => 'Keunggulan', 'en' => 'Why ZAP'], CompanyNavPosition::Header, 1, false],
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
                ['id' => 'Kulit terbaikmu dimulai dari sini', 'en' => 'Your best skin starts here'],
                ['id' => 'NgeZAP rutin dan dapatkan kulit terbaikmu dengan pilihan treatment terbaik dari ZAP.', 'en' => 'NgeZAP routinely and get your best skin with ZAP top treatments.'],
                1,
            ],
            [
                ['id' => 'Klinik kecantikan, laser, dan hair removal terpercaya', 'en' => 'Trusted beauty, laser, and hair removal clinic'],
                ['id' => 'Lebih dari 15 tahun pengalaman dengan teknologi terkini dan dokter bersertifikasi.', 'en' => 'Over 15 years of experience with latest technology and certified doctors.'],
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
            ['award', ['id' => 'Berpengalaman', 'en' => 'Experienced'], ['id' => 'Dengan lebih dari 15 tahun di dunia kecantikan, ZAP adalah destinasi terpercaya dan terlengkap untuk perawatan wajah, rambut, dan tubuh di Indonesia.', 'en' => 'With over 15 years in beauty, ZAP is the trusted and most complete destination for face, hair, and body care in Indonesia.']],
            ['smile', ['id' => 'Pelayanan Ramah', 'en' => 'Friendly Service'], ['id' => 'Staf kami selalu mengutamakan pelayanan personal dengan kenyamanan dan keramahan.', 'en' => 'Our staff always prioritizes personal service with comfort and friendliness.']],
            ['cpu', ['id' => 'Teknologi Terkini', 'en' => 'Latest Technology'], ['id' => 'ZAP menggunakan teknologi terbaik yang terbukti efektif, dengan standar medis yang ketat.', 'en' => 'ZAP uses proven best technology with strict medical standards.']],
            ['clock', ['id' => 'Tepat Waktu', 'en' => 'On Time'], ['id' => 'Waktumu berharga, dan ZAP selalu tepat waktu dalam setiap treatment.', 'en' => 'Your time is valuable, and ZAP is always on time for every treatment.']],
            ['map', ['id' => 'Ratusan Lokasi', 'en' => 'Hundreds of Locations'], ['id' => 'Lebih dari 100 outlet ZAP terintegrasi siap melayani Anda di seluruh Indonesia.', 'en' => 'Over 100 integrated ZAP outlets ready to serve you across Indonesia.']],
            ['shield-check', ['id' => 'Dokter Bersertifikasi', 'en' => 'Certified Doctors'], ['id' => 'Dokter ZAP berpengalaman, memprioritaskan keamanan, dan memberikan solusi efektif dengan penuh perhatian.', 'en' => 'ZAP doctors are experienced, prioritize safety, and deliver effective solutions with care.']],
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
            ['women-brazilian-vio-shr', ['id' => 'Women Brazilian (VIO) Super Hair Removal', 'en' => 'Women Brazilian (VIO) Super Hair Removal'], ['id' => 'Lebih maksimal untuk perawatan Brazilian Hair Removalmu, dengan tambahan teknologi combo yang ampuh untuk menghaluskan tekstur kulit area miss v.', 'en' => 'More maximal for your Brazilian Hair Removal, with combo technology that smoothens the skin texture of the intimate area.'], CompanyTreatmentBadge::Featured, ['Menghilangkan Bulu']],
            ['underarm-shr-body-rejuvenation', ['id' => 'Underarm Super Hair Removal + Body Rejuvenation', 'en' => 'Underarm Super Hair Removal + Body Rejuvenation'], ['id' => 'Perpaduan perawatan berbasis cahaya dengan target yang berbeda. Selain menghilangkan rambut pada area ketiak, perawatan ini juga membantu menyamarkan tekstur kulit.', 'en' => 'A blend of light-based treatments with different targets. Beyond removing underarm hair, it also helps smooth skin texture.'], CompanyTreatmentBadge::Current, ['Menghilangkan Bulu', 'Tekstur Kulit']],
            ['ipl-rejuvenation', ['id' => 'IPL Rejuvenation', 'en' => 'IPL Rejuvenation'], ['id' => "All time skin's favorite treatment! Menggunakan teknologi berbasis cahaya dengan spektrum luas.", 'en' => "All time skin's favorite treatment! Uses light-based technology with a broad spectrum."], null, ['glowing', 'rejuvenation']],
            ['photo-facial-glow', ['id' => 'Photo Facial Glow', 'en' => 'Photo Facial Glow'], ['id' => 'Nge-ZAP mu belum sempurna tanpa perawatan ini. Menggabungkan 3 teknologi terbaik dunia dalam 1 waktu yaitu Laser Q-Switched Nd:YAG, IPL Rejuvenation, dan Diamond Peel.', 'en' => 'Your Nge-ZAP is not complete without this. Combines 3 of the world best technologies in one session: Q-Switched Nd:YAG Laser, IPL Rejuvenation, and Diamond Peel.'], CompanyTreatmentBadge::Featured, ['rejuvenation', 'brightening']],
            ['photo-facial-acne', ['id' => 'Photo Facial Acne', 'en' => 'Photo Facial Acne'], ['id' => 'Atasi permasalahan peradanan jerawatmu dalam tahapan komplit.', 'en' => 'Tackle your acne inflammation problems in complete stages.'], CompanyTreatmentBadge::Current, ['acne solutions', 'oily skin']],
            ['double-pro-yellow-laser', ['id' => 'Double Pro Yellow Laser', 'en' => 'Double Pro Yellow Laser'], ['id' => 'Laser kuning yang bermanfaat untuk mencerahkan, mengatasi bekas jerawat kemerahan dan mengatasi flek.', 'en' => 'Yellow laser that brightens, reduces red acne scars, and addresses dark spots.'], CompanyTreatmentBadge::Featured, ['brightening', 'redness']],
            ['underarms-super-brightening', ['id' => 'Underarms Super Brightening', 'en' => 'Underarms Super Brightening'], ['id' => 'Perawatan ini dapat membantu mencerahkan ketiak secara efektif dan lebih cepat dibandingkan metode pengelupasan lainnya.', 'en' => 'This treatment helps brighten underarms effectively and faster than other peeling methods.'], CompanyTreatmentBadge::Current, ['rejuvenation', 'glowing']],
            ['underarm-hr-combo-shr-lhr', ['id' => 'Bundling Underarm Hair Removal Combo (SHR + LHR)', 'en' => 'Underarm Hair Removal Combo Bundle (SHR + LHR)'], ['id' => 'Buat perawatan Underarm Hair Removal kamu makin maksimal dengan tambahan teknologi combo yang ampuh untuk menghaluskan tekstur kulitmu.', 'en' => 'Make your Underarm Hair Removal more maximal with combo technology that smoothens your skin texture.'], CompanyTreatmentBadge::Featured, ['hair removal']],
            ['underarm-body-rejuvenation-combo', ['id' => 'Underarm Body Rejuvenation Combo (ZPL + Laser)', 'en' => 'Underarm Body Rejuvenation Combo (ZPL + Laser)'], ['id' => 'Kombinasi perawatan selalu lebih baik dan lebih cepat hasilnya dibandingkan dengan satu metode perawatan. Underarm Body Rejuvenation Combo menggabungkan ZPL dan Laser.', 'en' => 'Combo treatments are always better and faster than a single method. Underarm Body Rejuvenation Combo combines ZPL and Laser.'], CompanyTreatmentBadge::Current, ['brightening', 'rejuvenation']],
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
                'title' => ['id' => 'ZAP PHARMA SKINCARE MULAI DARI 85.000', 'en' => 'ZAP PHARMA SKINCARE STARTING FROM 85,000'],
                'description' => $this->richTextPair([
                    'id' => 'Optimalkan treatmentmu dengan racikan terbaik dari tim medis ZAP untuk perawatan intensifmu sehari-hari.',
                    'en' => 'Optimize your treatment with the best formulations from ZAP medical team for your daily intensive care.',
                ]),
                'image_path' => 'company-profile/demo/promo-zap-pharma.jpg',
                'cta_label' => ['id' => 'Lihat Produk', 'en' => 'See Products'],
                'cta_url' => 'https://estore.zapclinic.com',
                'cta_type' => CompanyCtaType::External,
                'is_active' => true,
            ],
        );
    }

    private function seedBrands(int $tenantId): void
    {
        $brands = [
            [['id' => 'ZAP Pharma', 'en' => 'ZAP Pharma'], ['id' => 'Skincare dan racikan tim medis ZAP.', 'en' => 'Skincare and formulations from ZAP medical team.']],
            [['id' => 'ZAP Clinic', 'en' => 'ZAP Clinic'], ['id' => 'Perawatan kecantikan, laser, dan hair removal.', 'en' => 'Beauty, laser, and hair removal treatments.']],
        ];

        foreach ($brands as $index => [$name, $description]) {
            CompanyBrand::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'sort_order' => $index + 1],
                [
                    'name' => $name,
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
            ['Wika Cahyaningtyas', 2024, ['id' => 'Kemarin saya coba Photo Facial glow, diamond peel & underarm combo semua nya suka, hasilnya memuaskan. Dokter & staff nya pun menyenangkan. Pelayanannya ramah.', 'en' => 'Yesterday I tried Photo Facial Glow, diamond peel & underarm combo — loved them all, satisfying results. Doctors & staff were delightful. Friendly service.']],
            ['Ellie Rosina', 2024, ['id' => 'Best service and treatments at ZAP, selalu di update perkembangaannya dan plan next treatmentnya biar lebih maksimal, semua staff & dokternya ramah', 'en' => 'Best service and treatments at ZAP, always updated on progress and planned next treatments for better results, all staff & doctors are friendly.']],
            ['Poppy Ludovita', 2015, ['id' => 'Semakin hari semakin on time, mengutamakan kenyamanan client, konsisten service nya', 'en' => 'More on time every day, prioritizing client comfort, consistent service.']],
            ['Husna', 2015, ['id' => 'Dari awal aku mempercayakan treatment wajah bersama ZAP, semua dokter dan staff ZAP selalu bikin aku merasa nyaman. Hal seperti ini nih yang nggak aku dapetin di klinik lain.', 'en' => 'From the start I trusted my facial treatments to ZAP, all ZAP doctors and staff always make me comfortable. This is what I do not get at other clinics.']],
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
                ['id' => 'ZAP PHARMA SKINCARE MULAI DARI 85.000', 'en' => 'ZAP PHARMA SKINCARE STARTING FROM 85,000'],
                ['id' => 'Optimalkan treatmentmu dengan racikan terbaik dari tim medis ZAP untuk perawatan intensifmu sehari-hari.', 'en' => 'Optimize your treatment with the best formulations from ZAP medical team for your daily intensive care.'],
                ['id' => 'Lihat Produk', 'en' => 'See Products'],
                CompanyCtaType::External,
                'https://estore.zapclinic.com',
            ],
            [
                CompanySectionKey::BookingCta,
                CompanySectionLayout::Banner,
                ['id' => '"ME TIME" KINI JADI LEBIH PRAKTIS', 'en' => '"ME TIME" IS NOW MORE PRACTICAL'],
                ['id' => 'Pesan treatment favoritmu sekarang melalui handphone untuk menghindari antrian dan pastikan kamu mendapatkan yang sesuai dengan jadwalmu.', 'en' => 'Book your favorite treatment now via your phone to skip the queue and get the time that fits your schedule.'],
                ['id' => 'Booking Sekarang', 'en' => 'Book Now'],
                CompanyCtaType::Whatsapp,
                self::WHATSAPP,
            ],
            [
                CompanySectionKey::EstoreCta,
                CompanySectionLayout::Banner,
                ['id' => 'VOUCHER TREATMENT DAN SKINCARE DIMANA SAJA', 'en' => 'TREATMENT VOUCHERS AND SKINCARE ANYWHERE'],
                ['id' => 'Kunjungi E-Store kami untuk mendapatkan voucher treatment favorit atau membeli produk skincare dengan mudah, kapan saja.', 'en' => 'Visit our E-Store to get your favorite treatment vouchers or buy skincare products easily, anytime.'],
                ['id' => 'Kunjungi Toko', 'en' => 'Visit Store'],
                CompanyCtaType::External,
                'https://estore.zapclinic.com',
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

<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\BroadcastReminderSetting;
use App\Models\CommissionRule;
use App\Models\CompanyBrand;
use App\Models\CompanyContentSection;
use App\Models\CompanyNavigationItem;
use App\Models\CompanyProfileSetting;
use App\Models\CompanyProfileSlide;
use App\Models\CompanyPromo;
use App\Models\CompanyTestimonial;
use App\Models\CompanyTreatment;
use App\Models\CompanyValueProp;
use App\Models\Expense;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\MedicalPhoto;
use App\Models\MedicalRecord;
use App\Models\MessageTemplate;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promo;
use App\Models\PromoItem;
use App\Models\ReminderRule;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\WahaSetting;
use App\Models\WhatsappSetting;
use App\Support\WahaClient;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Kolom morph menyimpan alias, bukan nama kelas penuh — memindahkan atau
     * mengganti nama model tidak boleh membuat baris lama nyasar.
     *
     * Peta ini dipakai `stock_movements.related` dan kolom subject/causer
     * activity log, jadi setiap model yang bisa jadi salah satunya harus
     * terdaftar: `enforceMorphMap` menolak model di luar daftar.
     *
     * @var array<string, class-string>
     */
    private const MORPH_MAP = [
        'tenant' => Tenant::class,
        'user' => User::class,
        'invitation' => Invitation::class,
        'service' => Service::class,
        'patient' => Patient::class,
        'booking' => Booking::class,
        'product' => Product::class,
        'promo' => Promo::class,
        'expense' => Expense::class,
        'commission_rule' => CommissionRule::class,
        'broadcast' => Broadcast::class,
        'broadcast_recipient' => BroadcastRecipient::class,
        'message_template' => MessageTemplate::class,
        'reminder_rule' => ReminderRule::class,
        'broadcast_reminder_setting' => BroadcastReminderSetting::class,
        'promo_item' => PromoItem::class,
        'stock_movement' => StockMovement::class,
        'transaction' => Transaction::class,
        'transaction_item' => TransactionItem::class,
        'payment' => Payment::class,
        'invoice' => Invoice::class,
        'medical_record' => MedicalRecord::class,
        'treatment_record' => TreatmentRecord::class,
        'medical_photo' => MedicalPhoto::class,
        'company_profile_setting' => CompanyProfileSetting::class,
        'company_navigation_item' => CompanyNavigationItem::class,
        'company_profile_slide' => CompanyProfileSlide::class,
        'company_value_prop' => CompanyValueProp::class,
        'company_treatment' => CompanyTreatment::class,
        'company_promo' => CompanyPromo::class,
        'company_brand' => CompanyBrand::class,
        'company_testimonial' => CompanyTestimonial::class,
        'company_content_section' => CompanyContentSection::class,
    ];

    /**
     * Klien WAHA disusun dari dua sumber: alamat dan API key milik platform,
     * nama sesi milik klinik yang sedang aktif. Bila salah satunya belum
     * diisi, yang diberikan null — pemanggilnya memperlakukan itu sebagai
     * "belum siap kirim", bukan galat.
     */
    public function register(): void
    {
        $this->app->bind(WahaClient::class, function (): ?WahaClient {
            $server = WahaSetting::query()->first();

            if ($server === null || ! $server->isConfigured()) {
                return null;
            }

            $tenant = WhatsappSetting::query()->first();

            if ($tenant === null || ! $tenant->isConfigured()) {
                return null;
            }

            return new WahaClient($server->base_url, $server->api_key, $tenant->session);
        });
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);
    }
}

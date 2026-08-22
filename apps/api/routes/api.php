<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingReminderSettingController;
use App\Http\Controllers\BroadcastAutoReminderController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CentralAuthController;
use App\Http\Controllers\CentralBackupController;
use App\Http\Controllers\CentralStatsController;
use App\Http\Controllers\CentralWahaSettingController;
use App\Http\Controllers\ChatbotSettingController;
use App\Http\Controllers\ClinicIdentityController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CompanyContentController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InboundMessageController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ReminderRuleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =========================================================================
// Publik (tanpa tenant prefix) — spec 001
// =========================================================================
Route::get('/translations', [TranslationController::class, 'index']);

// Route publik yang menyentuh kredensial atau membuat data harus dibatasi
// lajunya: tanpa itu, kata sandi bisa ditebak berulang tanpa hambatan dan
// pendaftaran klinik bisa dibanjiri. Batasnya per IP per menit.
//
// Pendaftaran dan penerimaan undangan dipatok lebih ketat daripada login:
// keduanya membuat data permanen, sementara salah ketik kata sandi adalah
// kejadian sehari-hari yang tidak boleh langsung mengunci orang.
Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('/register', [TenantRegistrationController::class, 'store']);
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
});

Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/central/login', [CentralAuthController::class, 'login']);
    // Tautan undangan dibuka berkali-kali oleh orang yang sama saat mengisi
    // formulir, jadi lajunya mengikuti login, bukan pendaftaran.
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
});

// Pesan masuk WhatsApp. Di luar grup {tenant} karena gateway tidak mengenal
// slug klinik; kliniknya ditentukan dari nama sesi pada payload, dan token
// ruas URL yang memisahkan panggilan sah dari panggilan sembarangan.
Route::post('/whatsapp/webhook/{token}', [InboundMessageController::class, 'store']);

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

// =========================================================================
// Central platform (spec 001) — auth:sanctum, platform admin
// =========================================================================
Route::middleware(['auth:sanctum', 'permission.team'])->prefix('central')->group(function (): void {
    Route::get('/stats', [CentralStatsController::class, 'index']);
    Route::get('/tenants', [PlatformTenantController::class, 'index']);
    Route::patch('/tenants/{tenant}/status', [PlatformTenantController::class, 'status']);
    // Satu dump memuat data seluruh klinik, jadi arsipnya hanya boleh
    // disentuh pengelola platform — bukan admin klinik mana pun.
    Route::get('/backups', [CentralBackupController::class, 'index']);
    Route::post('/backups', [CentralBackupController::class, 'store']);
    Route::get('/backups/{filename}/download', [CentralBackupController::class, 'download']);
    Route::delete('/backups/{filename}', [CentralBackupController::class, 'destroy']);

    // Server WAHA dipakai bersama seluruh klinik, jadi pengaturannya di sini.
    Route::get('/waha', [CentralWahaSettingController::class, 'show']);
    Route::put('/waha', [CentralWahaSettingController::class, 'update']);
});

// =========================================================================
// Company profile publik (spec 010) — tanpa auth, dibaca pengunjung
// =========================================================================
Route::prefix('{tenant}')
    ->middleware(['resolve.tenant', 'ensure.tenant.active', 'permission.team'])
    ->group(function (): void {
        Route::get('/profile', [CompanyProfileController::class, 'index']);
        Route::get('/profile/treatments/{slug}', [CompanyProfileController::class, 'showTreatment']);
    });

// =========================================================================
// Auth tenant-scoped (spec 001) — resolve tenant, tanpa auth untuk login
// =========================================================================
Route::prefix('{tenant}')->middleware(['resolve.tenant', 'ensure.tenant.active', 'permission.team'])->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Manajemen user tenant (spec 001)
Route::prefix('{tenant}')
    ->middleware(['resolve.tenant', 'ensure.tenant.active', 'permission.team', 'auth:sanctum'])
    ->group(function (): void {
        // Profil sendiri: preferensi tampilan, dibaca semua peran.
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/invite', [UserController::class, 'invite']);
        Route::post('/users/{user}/remove', [UserController::class, 'remove']);
        Route::patch('/users/{user}/role', [UserController::class, 'role']);
        Route::get('/invitations', [UserController::class, 'invitations']);
        Route::post('/invitations/{invitation}/cancel', [UserController::class, 'cancelInvitation']);
    });

// =========================================================================
// Klinik (spec 002) — tenant-scoped
// =========================================================================
Route::prefix('{tenant}/clinic')
    ->middleware(['resolve.tenant', 'ensure.tenant.active', 'permission.team', 'auth:sanctum'])
    ->group(function (): void {
        // US1 Staff
        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::put('staff/{staff}', [StaffController::class, 'update']);
        Route::delete('staff/{staff}', [StaffController::class, 'destroy']);
        Route::patch('staff/{staff}/role', [StaffController::class, 'updateRole']);
        Route::post('staff/{staff}/deactivate', [StaffController::class, 'deactivate']);

        // Izin peran klinik — matriks kecil, tanpa paginasi
        Route::get('roles', [RoleController::class, 'index']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::post('roles/{role}/reset', [RoleController::class, 'reset']);

        // US2 Service
        // Impor didaftarkan lebih dulu supaya `services/import/template`
        // tidak ditangkap `services/{service}` sebagai detail layanan.
        Route::post('services/import', [ServiceController::class, 'import']);
        Route::get('services/import/template', [ServiceController::class, 'importTemplate']);
        // Hapus permanen dipisah dari destroy yang mengarsipkan.
        Route::delete('services/{service}/force', [ServiceController::class, 'forceDestroy']);
        Route::apiResource('services', ServiceController::class);

        // US3 Patient
        Route::get('patients/{patient}/history', [PatientController::class, 'history']);
        Route::get('patients/{patient}/medical-records', [MedicalRecordController::class, 'patientRecords']);
        Route::apiResource('patients', PatientController::class);

        // US4 Booking
        Route::get('bookings/schedule', [BookingController::class, 'schedule']);
        // Di atas apiResource supaya "reminder-settings" tidak tertangkap
        // sebagai {booking}.
        Route::get('bookings/reminder-settings', [BookingReminderSettingController::class, 'show']);
        Route::put('bookings/reminder-settings', [BookingReminderSettingController::class, 'update']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::apiResource('bookings', BookingController::class);

        // US6 Product & Inventory
        Route::get('products/{product}/stock-movements', [StockMovementController::class, 'indexByProduct']);
        Route::post('products/{product}/stock-movements', [StockMovementController::class, 'store']);
        Route::post('products/import', [ProductController::class, 'import']);
        Route::get('products/import/template', [ProductController::class, 'importTemplate']);
        Route::delete('products/{product}/force', [ProductController::class, 'forceDestroy']);
        Route::apiResource('products', ProductController::class);

        // Pengeluaran klinik + aturan fee terapis
        Route::get('expenses/summary', [ExpenseController::class, 'summary']);
        Route::apiResource('expenses', ExpenseController::class);
        Route::get('commission-rules/calculate', [CommissionRuleController::class, 'calculate']);
        Route::get('commission-rules/setting', [CommissionRuleController::class, 'setting']);
        Route::put('commission-rules/setting', [CommissionRuleController::class, 'saveSetting']);
        Route::apiResource('commission-rules', CommissionRuleController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Kategori terpusat untuk layanan, produk, dan pengeluaran
        Route::delete('units/{unit}/force', [UnitController::class, 'forceDestroy']);
        Route::apiResource('units', UnitController::class);

        Route::delete('categories/{category}/force', [CategoryController::class, 'forceDestroy']);
        Route::apiResource('categories', CategoryController::class);

        // Broadcast WhatsApp — pengingat perawatan & promo ke pasien
        Route::get('broadcasts/audience-preview', [BroadcastController::class, 'preview']);
        Route::get('broadcasts/settings', [BroadcastController::class, 'settings']);
        Route::put('broadcasts/settings', [BroadcastController::class, 'updateSettings']);
        Route::get('broadcasts/auto-reminder', [BroadcastAutoReminderController::class, 'show']);
        Route::put('broadcasts/auto-reminder', [BroadcastAutoReminderController::class, 'update']);
        Route::get('broadcasts/connection', [BroadcastController::class, 'connection']);
        Route::get('broadcasts/dashboard', [BroadcastController::class, 'dashboard']);
        Route::post('broadcasts/test-message', [BroadcastController::class, 'sendTest']);
        Route::post('broadcasts/{broadcast}/send', [BroadcastController::class, 'send']);
        Route::patch('broadcasts/{broadcast}/status', [BroadcastController::class, 'changeStatus']);
        Route::apiResource('message-templates', MessageTemplateController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('reminder-rules', ReminderRuleController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::patch('broadcasts/{broadcast}/recipients/{recipient}', [BroadcastController::class, 'updateRecipient']);
        Route::apiResource('broadcasts', BroadcastController::class)->only(['index', 'store', 'show', 'destroy']);

        // Promo — potongan harga layanan/produk dalam rentang tanggal
        Route::apiResource('promos', PromoController::class);

        // US5 POS / Transaction
        Route::get('transactions/{transaction}/invoice', [InvoiceController::class, 'show']);
        Route::post('transactions/{transaction}/invoice/print', [InvoiceController::class, 'recordPrint']);
        Route::get('transactions/{transaction}/stock-movements', [StockMovementController::class, 'indexByTransaction']);
        Route::post('transactions/{transaction}/payments', [PaymentController::class, 'store']);
        Route::post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);
        Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show', 'destroy']);

        // US7 Medical Records
        Route::get('medical-records', [MedicalRecordController::class, 'index']);
        Route::post('medical-records', [MedicalRecordController::class, 'store']);
        Route::get('medical-records/{medicalRecord}', [MedicalRecordController::class, 'show']);
        Route::patch('medical-records/{medicalRecord}', [MedicalRecordController::class, 'update']);
        Route::delete('medical-records/{medicalRecord}', [MedicalRecordController::class, 'destroy']);
        Route::post('medical-records/{medicalRecord}/treatments', [MedicalRecordController::class, 'addTreatment']);
        Route::post('medical-records/{medicalRecord}/photos', [MedicalRecordController::class, 'addPhoto']);
        Route::delete('medical-records/{medicalRecord}/photos/{medicalPhoto}', [MedicalRecordController::class, 'deletePhoto'])
            ->scopeBindings();

        // Identitas klinik untuk brand sidebar — dibaca semua peran, jadi
        // diletakkan di luar prefix company-profile yang dijaga content.view.
        Route::get('identity', [ClinicIdentityController::class, 'show']);

        // Company profile CMS (spec 010)
        Route::prefix('company-profile')->group(function (): void {
            Route::get('settings', [CompanyContentController::class, 'settings']);
            Route::put('settings', [CompanyContentController::class, 'updateSettings']);
            Route::post('settings/publish', [CompanyContentController::class, 'togglePublish']);
            Route::post('media', [CompanyContentController::class, 'upload']);

            // {entity} dibatasi daftar di CompanyContentRegistry; nilai lain 404.
            Route::post('{entity}/reorder', [CompanyContentController::class, 'reorder']);
            Route::post('{entity}/{content}/toggle', [CompanyContentController::class, 'toggle']);
            Route::get('{entity}', [CompanyContentController::class, 'index']);
            Route::post('{entity}', [CompanyContentController::class, 'store']);
            Route::get('{entity}/{content}', [CompanyContentController::class, 'show']);
            Route::put('{entity}/{content}', [CompanyContentController::class, 'update']);
            Route::delete('{entity}/{content}', [CompanyContentController::class, 'destroy']);
        });

        // Dasbor klinik — dibaca semua peran, tanpa permission modul
        Route::get('dashboard/summary', [DashboardController::class, 'summary']);

        // Log aktivitas — baca saja, semua modul dalam satu daftar
        Route::get('activity-logs/filters', [ActivityLogController::class, 'filters']);
        Route::get('activity-logs', [ActivityLogController::class, 'index']);
        Route::get('activity-logs/{activity}', [ActivityLogController::class, 'show'])->whereNumber('activity');

        // Statistik kepala halaman index — satu endpoint, modul dari segmen URL
        Route::get('stats/{module}', [StatsController::class, 'show']);

        // Chatbot WhatsApp — setelan agen dan layanan yang boleh dibooking
        Route::get('chatbot/settings', [ChatbotSettingController::class, 'show']);
        Route::put('chatbot/settings', [ChatbotSettingController::class, 'update']);
        Route::post('chatbot/settings/avatar', [ChatbotSettingController::class, 'avatar']);

        // US8 Reports
        Route::get('reports/monthly', [ReportController::class, 'monthly']);
        Route::get('reports/monthly/export', [ReportController::class, 'monthlyExport']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/services', [ReportController::class, 'services']);
        Route::get('reports/products', [ReportController::class, 'products']);
    });

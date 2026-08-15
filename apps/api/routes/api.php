<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CentralAuthController;
use App\Http\Controllers\CentralStatsController;
use App\Http\Controllers\CommissionRuleController;
use App\Http\Controllers\CompanyContentController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =========================================================================
// Publik (tanpa tenant prefix) — spec 001
// =========================================================================
Route::get('/translations', [TranslationController::class, 'index']);
Route::post('/register', [TenantRegistrationController::class, 'store']);
Route::post('/central/login', [CentralAuthController::class, 'login']);
Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

// =========================================================================
// Central platform (spec 001) — auth:sanctum, platform admin
// =========================================================================
Route::middleware(['auth:sanctum', 'permission.team'])->prefix('central')->group(function (): void {
    Route::get('/stats', [CentralStatsController::class, 'index']);
    Route::get('/tenants', [PlatformTenantController::class, 'index']);
    Route::patch('/tenants/{tenant}/status', [PlatformTenantController::class, 'status']);
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
    Route::post('/login', [AuthController::class, 'login']);
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
        Route::patch('staff/{staff}/role', [StaffController::class, 'updateRole']);
        Route::post('staff/{staff}/deactivate', [StaffController::class, 'deactivate']);

        // US2 Service
        Route::apiResource('services', ServiceController::class);

        // US3 Patient
        Route::get('patients/{patient}/history', [PatientController::class, 'history']);
        Route::get('patients/{patient}/medical-records', [MedicalRecordController::class, 'patientRecords']);
        Route::apiResource('patients', PatientController::class);

        // US4 Booking
        Route::get('bookings/schedule', [BookingController::class, 'schedule']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::apiResource('bookings', BookingController::class);

        // US6 Product & Inventory
        Route::get('products/{product}/stock-movements', [StockMovementController::class, 'indexByProduct']);
        Route::post('products/{product}/stock-movements', [StockMovementController::class, 'store']);
        Route::apiResource('products', ProductController::class);

        // Pengeluaran klinik + aturan fee terapis
        Route::get('expenses/summary', [ExpenseController::class, 'summary']);
        Route::apiResource('expenses', ExpenseController::class);
        Route::get('commission-rules/calculate', [CommissionRuleController::class, 'calculate']);
        Route::apiResource('commission-rules', CommissionRuleController::class)
            ->only(['index', 'store', 'update', 'destroy']);

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

        // Statistik kepala halaman index — satu endpoint, modul dari segmen URL
        Route::get('stats/{module}', [StatsController::class, 'show']);

        // US8 Reports
        Route::get('reports/monthly', [ReportController::class, 'monthly']);
        Route::get('reports/monthly/export', [ReportController::class, 'monthlyExport']);
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/services', [ReportController::class, 'services']);
        Route::get('reports/products', [ReportController::class, 'products']);
    });

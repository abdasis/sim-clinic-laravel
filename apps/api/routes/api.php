<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CentralAuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlatformTenantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
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
Route::middleware('auth:sanctum')->prefix('central')->group(function (): void {
    Route::get('/tenants', [PlatformTenantController::class, 'index']);
    Route::patch('/tenants/{tenant}/status', [PlatformTenantController::class, 'status']);
});

// =========================================================================
// Auth tenant-scoped (spec 001) — resolve tenant, tanpa auth untuk login
// =========================================================================
Route::prefix('{tenant}')->middleware('resolve.tenant')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Manajemen user tenant (spec 001)
Route::prefix('{tenant}')
    ->middleware(['resolve.tenant', 'ensure.tenant.active', 'auth:sanctum'])
    ->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/invite', [UserController::class, 'invite']);
        Route::post('/users/{user}/remove', [UserController::class, 'remove']);
        Route::patch('/users/{user}/role', [UserController::class, 'role']);
    });

// =========================================================================
// Klinik (spec 002) — tenant-scoped
// =========================================================================
Route::prefix('{tenant}/clinic')
    ->middleware(['resolve.tenant', 'ensure.tenant.active', 'auth:sanctum'])
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
        Route::get('patients/{patient}/treatments', [MedicalRecordController::class, 'patientTreatments']);
        Route::apiResource('patients', PatientController::class)->except(['destroy']);

        // US4 Booking
        Route::get('bookings/schedule', [BookingController::class, 'schedule']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::apiResource('bookings', BookingController::class);

        // US6 Product & Inventory
        Route::get('products/{product}/stock-movements', [StockMovementController::class, 'indexByProduct']);
        Route::post('products/{product}/stock-movements', [StockMovementController::class, 'store']);
        Route::apiResource('products', ProductController::class);

        // US5 POS / Transaction
        Route::get('transactions/{transaction}/invoice', [InvoiceController::class, 'show']);
        Route::post('transactions/{transaction}/payments', [PaymentController::class, 'store']);
        Route::post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);
        Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);

        // US7 Medical Records
        Route::post('medical-records', [MedicalRecordController::class, 'store']);
        Route::post('medical-records/{medicalRecord}/treatments', [MedicalRecordController::class, 'addTreatment']);
        Route::post('medical-records/{medicalRecord}/photos', [MedicalRecordController::class, 'addPhoto']);

        // US8 Reports
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/services', [ReportController::class, 'services']);
        Route::get('reports/products', [ReportController::class, 'products']);
    });

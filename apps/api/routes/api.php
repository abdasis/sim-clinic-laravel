<?php

use App\Http\Controllers\DemoDataTableController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Publik ---
Route::get('/translations', [TranslationController::class, 'index']);
Route::get('/demo/data', [DemoDataTableController::class, 'index']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- Klinik (spec 002), tenant-scoped ---
Route::prefix('{tenant}/clinic')
    ->middleware(['resolve.tenant', 'ensure.tenant.active', 'auth:sanctum'])
    ->group(function (): void {
        Route::apiResource('services', ServiceController::class);
    });

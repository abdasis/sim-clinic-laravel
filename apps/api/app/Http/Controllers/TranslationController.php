<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bagikan seluruh grup terjemahan ke frontend SPA (CLAUDE.md i18n).
 * Pengganti share HandleInertiaRequests untuk arsitektur Sanctum SPA.
 */
class TranslationController extends Controller
{
    private const GROUPS = [
        'general', 'auth', 'tenant', 'central', 'validation',
        'clinic', 'staff', 'service', 'patient', 'booking',
        'medical_record', 'product', 'inventory', 'pos', 'invoice', 'report',
        'company_profile', 'dashboard', 'brand', 'stats', 'cta', 'preferences',
    ];

    /**
     * Bahasa yang punya berkas terjemahan lengkap. Nilai di luar daftar
     * diabaikan supaya `?locale=` tidak bisa dipakai menebak berkas lain.
     */
    private const LOCALES = ['id', 'en'];

    public function index(Request $request): JsonResponse
    {
        $locale = $request->query('locale');
        $locale = in_array($locale, self::LOCALES, true) ? $locale : app()->getLocale();

        $translations = [];

        foreach (self::GROUPS as $group) {
            $translations[$group] = __($group, [], $locale);
        }

        return response()->json([
            'data' => $translations,
            'meta' => ['locale' => $locale],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Bagikan seluruh grup terjemahan ke frontend SPA (CLAUDE.md i18n).
 * Pengganti share HandleInertiaRequests untuk arsitektur Sanctum SPA.
 */
class TranslationController extends Controller
{
    private const GROUPS = [
        'general', 'auth', 'tenant', 'validation',
        'clinic', 'staff', 'service', 'patient', 'booking',
        'medical_record', 'product', 'inventory', 'pos', 'invoice', 'report',
    ];

    public function index(): JsonResponse
    {
        $translations = [];

        foreach (self::GROUPS as $group) {
            $translations[$group] = __($group);
        }

        return response()->json(['data' => $translations]);
    }
}

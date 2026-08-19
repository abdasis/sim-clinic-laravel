<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClinicIdentityResource;
use App\Services\CompanyProfileService;
use Illuminate\Http\JsonResponse;

/**
 * Identitas klinik yang sedang aktif — nama, logo, kontak.
 *
 * Sengaja terpisah dari CompanyContentController: yang di sana dijaga
 * `content.view`, izin milik admin. Identitas justru dibaca semua peran,
 * karena nama dan logonya menempel di sidebar setiap halaman klinik.
 * Otorisasinya cukup dari grup rute klinik: siapa pun yang sudah lolos ke
 * dalam klinik boleh tahu klinik mana yang sedang dibukanya.
 */
class ClinicIdentityController extends Controller
{
    public function show(CompanyProfileService $profiles): JsonResponse
    {
        $tenant = $profiles->identity(app('tenant'));

        return response()->json([
            'data' => new ClinicIdentityResource($tenant),
            'meta' => [],
        ]);
    }
}

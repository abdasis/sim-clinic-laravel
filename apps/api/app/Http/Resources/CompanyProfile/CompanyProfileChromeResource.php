<?php

namespace App\Http\Resources\CompanyProfile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kop dan kaki halaman publik: identitas klinik beserta menunya.
 *
 * Dipakai halaman selain beranda yang tetap perlu berdiri sebagai bagian
 * dari situs — mengirim seluruh isi beranda hanya untuk mendapatkan menunya
 * jauh lebih mahal daripada potongan ini.
 */
class CompanyProfileChromeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'settings' => $data['settings']
                ? new CompanyProfileSettingResource($data['settings'])
                : null,
            'navigation' => [
                'header' => CompanyNavigationItemResource::collection($data['navigation']['header']),
                'footer' => CompanyNavigationItemResource::collection($data['navigation']['footer']),
            ],
        ];
    }
}

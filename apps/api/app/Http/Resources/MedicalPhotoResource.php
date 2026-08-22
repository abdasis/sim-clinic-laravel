<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu foto klinis pada rekam medis.
 *
 * `type_label` ikut dikirim supaya sisi web tidak perlu menerjemahkan
 * sendiri "before"/"after" — istilahnya sudah punya rumah di berkas bahasa,
 * dan menyalinnya ke frontend berarti dua tempat yang bisa berbeda.
 */
class MedicalPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type?->label(),
            'path' => $this->path,
            'url' => $this->url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

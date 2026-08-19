<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Sertakan izin walau belum ada sesi terautentikasi.
     *
     * Dipakai jalur login: token baru saja diterbitkan, jadi `$request->user()`
     * masih kosong padahal jelas ini akun yang bersangkutan.
     */
    private bool $forcePermissions = false;

    public static function withPermissions(mixed $resource): self
    {
        $instance = new self($resource);
        $instance->forcePermissions = true;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'clinic_role' => $this->clinic_role,
            'appearance' => $this->appearance,
            // Hanya untuk dirinya sendiri: dipakai frontend menyusun menu,
            // dan daftar staf tidak perlu tahu izin orang lain — apalagi
            // dengan satu kueri per baris.
            'permissions' => $this->when(
                $this->forcePermissions || $request->user()?->getKey() === $this->getKey(),
                fn () => $this->getAllPermissions()->pluck('name')->sort()->values(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

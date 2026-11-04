<?php

namespace App\Http\Requests;

use App\Models\Broadcast;

/**
 * Sunting broadcast. Isi formulirnya sama dengan pembuatan; bedanya poster
 * boleh tetap memakai yang lama, jadi batas panjang pesan bergambar dihitung
 * dari poster yang tersimpan — bukan hanya dari berkas yang diunggah sekarang.
 */
class BroadcastUpdateRequest extends BroadcastRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            // Poster bisa dibuang tanpa mengunggah pengganti.
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'remove_image' => __('broadcast.remove_image'),
        ]);
    }

    protected function hasImage(): bool
    {
        if ($this->hasFile('image')) {
            return true;
        }

        if ($this->boolean('remove_image')) {
            return false;
        }

        $route = $this->route('broadcast');
        $key = $route instanceof Broadcast ? $route->getKey() : $route;

        return Broadcast::query()->whereKey($key)->value('image_path') !== null;
    }
}

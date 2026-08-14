<?php

namespace App\Http\Requests\CompanyProfile;

class ReorderRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            // Urutan dikirim utuh sebagai daftar id; mengirim sebagian
            // membuat sisanya menggantung di posisi lama.
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ];
    }
}

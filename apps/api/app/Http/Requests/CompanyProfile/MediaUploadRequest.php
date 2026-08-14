<?php

namespace App\Http\Requests\CompanyProfile;

class MediaUploadRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            // Menentukan subfolder penyimpanan, bukan entitas yang diubah.
            'entity' => ['required', 'string', 'alpha_dash', 'max:50'],
        ];
    }
}

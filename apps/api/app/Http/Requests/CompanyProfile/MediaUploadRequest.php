<?php

namespace App\Http\Requests\CompanyProfile;

class MediaUploadRequest extends CompanyContentRequest
{
    public function rules(): array
    {
        return [
            // SVG sengaja tidak diterima. Berkasnya adalah dokumen XML yang
            // boleh memuat <script> dan atribut event, dan berkas ini disajikan
            // dari domain yang sama dengan aplikasi — satu logo berisi skrip
            // cukup untuk mencuri token siapa pun yang membuka halamannya.
            // Logo yang butuh ketajaman vektor dikirim sebagai PNG transparan.
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Menentukan subfolder penyimpanan, bukan entitas yang diubah.
            'entity' => ['required', 'string', 'alpha_dash', 'max:50'],
        ];
    }
}

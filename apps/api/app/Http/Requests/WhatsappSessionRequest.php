<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Models\WhatsappSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WhatsappSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Mengubah sesi yang sudah ada, bukan membuat broadcast baru. Izin
        // buat lebih longgar daripada izin ubah di sebagian peran klinik, jadi
        // kategori yang salah di sini berarti pengaturan pengiriman bisa
        // disentuh oleh peran yang tidak seharusnya.
        return $this->user()?->can('update', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        $currentId = WhatsappSetting::query()->first()?->id;

        return [
            // Kosong berarti klinik ini belum punya sesi gateway; mengisinya
            // memulai alur koneksi. Formatnya mengikuti yang diterima gateway:
            // huruf kecil, angka, strip.
            'session' => [
                'nullable',
                'string',
                'regex:/^[a-z0-9-]{3,64}$/',
                // Satu sesi hanya boleh dipakai satu klinik: dua klinik yang
                // menunjuk sesi sama akan mengirim dari nomor yang sama.
                // Tanpa aturan ini yang muncul bukan pesan galat, melainkan
                // pelanggaran unique di basis data.
                Rule::unique('whatsapp_settings', 'session')->ignore($currentId),
            ],
            // Kosong berarti tanpa batas harian.
            'daily_message_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            // Kosong berarti API key lama dipertahankan, bukan dihapus:
            // formulir klinik hanya mengirimnya saat admin menggantinya.
            'api_key' => ['nullable', 'string', 'min:8', 'max:255'],
            // Alamat server gateway yang dipakai seluruh klinik. Boleh http
            // untuk pemasangan di jaringan internal.
            'base_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * Slug sesi terkunci setelah sesi gateway dibuat: gateway memetakan sesi
     * lewat slug itu, jadi menggantinya diam-diam meninggalkan sesi lama
     * yang masih tertaut nomor klinik.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $setting = WhatsappSetting::query()->first();

            if ($setting === null || blank($setting->gateway_session_id)) {
                return;
            }

            $session = $this->input('session');

            if ($this->has('session') && $session !== $setting->session) {
                $validator->errors()->add('session', __('broadcast.session_locked'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'session' => __('broadcast.session_label'),
            'api_key' => __('whatsapp_gateway.api_key'),
            'base_url' => __('whatsapp_gateway.base_url'),
        ];
    }

    public function messages(): array
    {
        return [
            'session.regex' => __('broadcast.session_format'),
            'session.unique' => __('broadcast.session_taken'),
        ];
    }
}

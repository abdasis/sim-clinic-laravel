<?php

namespace App\Http\Requests;

use App\Enums\BroadcastAudience;
use App\Models\Broadcast;
use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BroadcastPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Broadcast::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::enum(BroadcastAudience::class)],
            'days' => ['nullable', 'integer', 'min:1', 'max:730'],
            // Layanan klinik lain tidak boleh dipakai menyaring penerima:
            // jumlah dan nama pasien yang muncul jadi bocoran lintas klinik.
            'service_id' => ['nullable', TenantRule::exists('services')],
            // Pasien klinik lain ditolak dengan alasan yang sama seperti
            // layanan: nama dan nomor yang muncul di pratinjau jadi bocoran
            // lintas klinik.
            'patient_ids' => ['nullable', 'array', 'max:500'],
            'patient_ids.*' => [TenantRule::exists('patients')],
        ];
    }
}

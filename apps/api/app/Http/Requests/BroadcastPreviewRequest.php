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
        ];
    }
}

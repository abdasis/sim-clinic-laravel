<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BroadcastRecipientStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Broadcast::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Status penerima ditandai manual oleh admin saat pengiriman
            // dilakukan lewat wa.me, jadi hanya tiga keadaan ini yang masuk
            // akal untuk ditulis tangan.
            'status' => ['required', Rule::in(['sent', 'skipped', 'pending'])],
        ];
    }
}

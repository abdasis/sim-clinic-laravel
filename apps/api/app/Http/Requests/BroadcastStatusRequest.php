<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BroadcastStatusRequest extends FormRequest
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
            // Ready dan done tidak disebut: keduanya keadaan yang dicapai
            // sistem, bukan yang boleh dipilih admin.
            'status' => ['required', Rule::in(['sending', 'paused', 'cancelled'])],
        ];
    }
}

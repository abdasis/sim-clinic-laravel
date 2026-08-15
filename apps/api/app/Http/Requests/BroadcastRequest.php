<?php

namespace App\Http\Requests;

use App\Enums\BroadcastAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class BroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // WhatsApp memutus pesan sangat panjang; 4000 aman untuk semua gateway.
            'message' => ['required', 'string', 'max:4000'],
            'audience' => ['required', new Enum(BroadcastAudience::class)],
            'audience_params' => ['nullable', 'array'],
            'audience_params.days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'audience_params.service_id' => ['nullable', 'exists:services,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $audience = $this->input('audience');
            $params = $this->input('audience_params', []);

            if ($audience === BroadcastAudience::Inactive->value && empty($params['days'])) {
                $validator->errors()->add('audience_params.days', __('broadcast.days_required'));
            }

            if ($audience === BroadcastAudience::Service->value && empty($params['service_id'])) {
                $validator->errors()->add('audience_params.service_id', __('broadcast.service_required'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'title' => __('broadcast.title_field'),
            'message' => __('broadcast.message'),
            'audience' => __('broadcast.audience_label'),
            'audience_params.days' => __('broadcast.days'),
            'audience_params.service_id' => __('service.title'),
        ];
    }
}

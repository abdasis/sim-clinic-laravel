<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Support\TemplateVariables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BroadcastTestRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:32'],
            'template' => ['required', 'array'],
            'template.body' => ['required', 'string', 'max:4000'],
            'template.params' => ['nullable', 'array', 'max:10'],
            'template.params.*' => ['string', Rule::in(TemplateVariables::KEYS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $template = $this->input('template');

            if (! is_array($template)) {
                return;
            }

            $body = (string) ($template['body'] ?? '');
            $placeholders = TemplateVariables::placeholderCount($body);
            $params = (array) ($template['params'] ?? []);

            if ($placeholders !== count($params)) {
                $validator->errors()->add(
                    'template.params',
                    __('broadcast.template_params_mismatch', [
                        'placeholders' => $placeholders,
                        'params' => count($params),
                    ]),
                );
            } elseif (! TemplateVariables::numbersAreContiguous($body)) {
                $validator->errors()->add(
                    'template.params',
                    __('broadcast.template_placeholders_not_contiguous'),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => __('broadcast.test_phone_label'),
            'template' => __('broadcast.template_label'),
            'template.body' => __('broadcast.template_body'),
            'template.params' => __('broadcast.template_params'),
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\BroadcastAudience;
use App\Enums\BroadcastKind;
use App\Rules\TenantRule;
use App\Support\TemplateVariables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            // Template resmi Meta yang dipakai saat pengiriman. Body-nya juga
            // menjadi snapshot pesan yang disimpan per penerima.
            'template' => ['required', 'array'],
            'template.name' => ['required', 'string', 'max:255'],
            'template.language' => ['required', 'string', 'max:20'],
            // WhatsApp memutus pesan sangat panjang; 4000 aman untuk semua gateway.
            'template.body' => ['required', 'string', 'max:4000'],
            'template.header_type' => ['nullable', Rule::in(TemplateVariables::HEADER_TYPES)],
            'template.params' => ['nullable', 'array', 'max:10'],
            'template.params.*' => ['string', Rule::in(TemplateVariables::KEYS)],
            // Poster promo; batasnya sama dengan foto rekam medis, dan
            // gambarnya dikirim sebagai tautan header template.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'audience' => ['required', new Enum(BroadcastAudience::class)],
            'kind' => ['nullable', new Enum(BroadcastKind::class)],
            'audience_params' => ['nullable', 'array'],
            'audience_params.days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'audience_params.service_id' => ['nullable', TenantRule::exists('services')],
            'audience_params.patient_ids' => ['nullable', 'array', 'max:500'],
            'audience_params.patient_ids.*' => [TenantRule::exists('patients')],
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

            // Broadcast ke kontak terpilih tanpa satu pun kontak akan lolos
            // sebagai kampanye kosong yang berstatus selesai tanpa mengirim
            // apa pun — lebih baik ditolak di formulirnya.
            if ($audience === BroadcastAudience::Selected->value && empty($params['patient_ids'])) {
                $validator->errors()->add('audience_params.patient_ids', __('broadcast.patients_required'));
            }

            $this->validateTemplate($validator);
        });
    }

    /**
     * Jumlah placeholder harus sama dengan jumlah parameter: Meta menolak
     * template yang parameternya kurang atau berlebih, dan penolakannya baru
     * terlihat setelah seluruh antrean terbentuk.
     */
    protected function validateTemplate(Validator $validator): void
    {
        $template = $this->input('template');

        if (! is_array($template)) {
            return;
        }

        $body = (string) ($template['body'] ?? '');
        $params = (array) ($template['params'] ?? []);
        $placeholders = TemplateVariables::placeholderCount($body);

        if ($placeholders !== count($params)) {
            $validator->errors()->add(
                'template.params',
                __('broadcast.template_params_mismatch', [
                    'placeholders' => $placeholders,
                    'params' => count($params),
                ]),
            );
        }

        if (($template['header_type'] ?? null) === 'IMAGE' && $this->imageMissing()) {
            $validator->errors()->add('image', __('broadcast.template_image_required'));
        }
    }

    /**
     * Template bergambar butuh berkas. Saat menyunting, poster lama yang
     * masih menempel juga dihitung — lihat BroadcastUpdateRequest.
     */
    protected function imageMissing(): bool
    {
        return ! $this->hasFile('image');
    }

    public function attributes(): array
    {
        return [
            'title' => __('broadcast.title_field'),
            'template' => __('broadcast.template_label'),
            'template.name' => __('broadcast.template_name'),
            'template.language' => __('broadcast.template_language'),
            'template.body' => __('broadcast.template_body'),
            'template.params' => __('broadcast.template_params'),
            'template.header_type' => __('broadcast.template_header_type'),
            'image' => __('broadcast.image'),
            'audience' => __('broadcast.audience_label'),
            'audience_params.days' => __('broadcast.days'),
            'audience_params.patient_ids' => __('broadcast.selected_patients'),
            'audience_params.service_id' => __('service.title'),
        ];
    }
}

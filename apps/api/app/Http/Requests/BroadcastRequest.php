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
    /** Batas panjang pesan tanpa lampiran gambar. */
    protected const BODY_MAX = 4000;

    /** Batas lebih pendek saat ada gambar: caption ikut ditampilkan di bawahnya. */
    protected const BODY_MAX_WITH_IMAGE = 1024;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Pesan disusun bebas dari klinik, tanpa template yang disetujui
            // Meta. Body-nya juga menjadi snapshot pesan per penerima.
            'template' => ['required', 'array'],
            // WhatsApp memutus pesan sangat panjang; 4000 aman untuk gateway.
            'template.body' => ['required', 'string', 'max:4000'],
            'template.params' => ['nullable', 'array', 'max:10'],
            'template.params.*' => ['string', Rule::in(TemplateVariables::KEYS)],
            // Poster promo; batasnya sama dengan foto rekam medis, dan
            // gambarnya dikirim sebagai lampiran pesan.
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
     * Jumlah placeholder harus sama dengan jumlah parameter: pesan yang
     * penandanya tidak terisi akan sampai apa adanya, dan kekurangannya baru
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
        } elseif (! TemplateVariables::numbersAreContiguous($body)) {
            $validator->errors()->add(
                'template.params',
                __('broadcast.template_placeholders_not_contiguous'),
            );
        }

        $max = $this->hasImage() ? self::BODY_MAX_WITH_IMAGE : self::BODY_MAX;

        if (mb_strlen($body) > $max) {
            $validator->errors()->add(
                'template.body',
                __('broadcast.template_body_too_long', ['max' => $max]),
            );
        }
    }

    /**
     * Poster yang akan ikut terkirim. Saat menyunting, poster lama yang masih
     * menempel juga dihitung — lihat BroadcastUpdateRequest.
     */
    protected function hasImage(): bool
    {
        return $this->hasFile('image');
    }

    public function attributes(): array
    {
        return [
            'title' => __('broadcast.title_field'),
            'template' => __('broadcast.template_label'),
            'template.body' => __('broadcast.template_body'),
            'template.params' => __('broadcast.template_params'),
            'image' => __('broadcast.image'),
            'audience' => __('broadcast.audience_label'),
            'audience_params.days' => __('broadcast.days'),
            'audience_params.patient_ids' => __('broadcast.selected_patients'),
            'audience_params.service_id' => __('service.title'),
        ];
    }
}

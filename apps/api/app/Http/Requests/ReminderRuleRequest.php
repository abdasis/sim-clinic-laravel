<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReminderRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Satu kelas melayani store dan update, jadi izin yang diperiksa
        // mengikuti ada-tidaknya aturan yang sedang diubah pada route.
        $ability = $this->route('reminderRule') !== null ? 'update' : 'create';

        return $this->user()?->can($ability, Broadcast::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Layanan hanya dipilih saat aturannya dibuat: memindahkan aturan ke
        // layanan lain sama saja dengan membuat aturan baru, dan
        // membiarkannya berubah akan menabrak unique tanpa penjelasan.
        $onCreate = $this->route('reminderRule') === null;

        return array_filter([
            // Tanpa TenantRule, `exists:` lolos untuk layanan klinik lain:
            // aturannya berjalan lewat query builder, jadi TenantScope tidak
            // ikut berlaku.
            //
            // Unique-nya sengaja tetap global: satu layanan hanya dimiliki
            // satu klinik, jadi "satu aturan per layanan" sudah otomatis
            // berarti "per klinik".
            'service_id' => $onCreate
                ? ['required', TenantRule::exists('services'), Rule::unique('reminder_rules', 'service_id')]
                : null,
            'days_after' => ['required', 'integer', 'min:1', 'max:730'],
            'message_template_id' => ['nullable', TenantRule::exists('message_templates')],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'service_id' => __('service.title'),
            'days_after' => __('broadcast.reminder_days'),
        ];
    }
}

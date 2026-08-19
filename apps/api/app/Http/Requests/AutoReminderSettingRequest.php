<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Rules\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class AutoReminderSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Menyimpan satu setelan yang sudah ada bentuknya, bukan membuat
        // broadcast baru — izin yang diperiksa harus izin ubah.
        return $this->user()?->can('update', Broadcast::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:730'],
            // Templat milik klinik lain tidak boleh dirujuk: isinya akan
            // terkirim ke pasien klinik ini atas nama klinik ini.
            'message_template_id' => ['nullable', TenantRule::exists('message_templates')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'days' => __('broadcast.auto_reminder_days'),
            'message_template_id' => __('broadcast.auto_reminder_template'),
            'is_active' => __('broadcast.auto_reminder_active'),
        ];
    }
}

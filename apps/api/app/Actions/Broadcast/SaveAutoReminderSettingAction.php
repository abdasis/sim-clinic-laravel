<?php

namespace App\Actions\Broadcast;

use App\Actions\LogAuditAction;
use App\Models\BroadcastReminderSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan pengaturan follow-up otomatis klinik. Satu baris per klinik, jadi
 * yang ada diperbarui, bukan ditambah.
 */
class SaveAutoReminderSettingAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): BroadcastReminderSetting
    {
        $setting = BroadcastReminderSetting::query()->firstOrNew([]);
        $existed = $setting->exists;
        $old = $setting->only(['days', 'message_template_id', 'is_active']);

        $setting->fill([
            'days' => $data['days'],
            'message_template_id' => $data['message_template_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ])->save();

        $new = $setting->only(['days', 'message_template_id', 'is_active']);

        app(LogAuditAction::class)->handle(
            $existed ? 'broadcast.auto_reminder.updated' : 'broadcast.auto_reminder.created',
            $setting,
            Auth::user(),
            $existed ? ['old' => $old, 'new' => $new] : ['new' => $new],
            $this->describe($setting, $existed),
        );

        return $setting->load('template');
    }

    private function describe(BroadcastReminderSetting $setting, bool $existed): string
    {
        $state = $setting->is_active ? 'aktif' : 'nonaktif';

        return ($existed ? 'Memperbarui' : 'Mengatur')
            .' follow-up otomatis: pasien diingatkan setelah '
            .$setting->days.' hari sejak tindakan terakhir ('.$state.').';
    }
}

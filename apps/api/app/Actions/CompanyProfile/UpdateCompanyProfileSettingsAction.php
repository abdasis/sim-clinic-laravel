<?php

namespace App\Actions\CompanyProfile;

use App\Actions\LogAuditAction;
use App\Models\CompanyProfileSetting;
use Illuminate\Support\Facades\Log;

/**
 * Simpan setelan company profile. Barisnya singleton per tenant, jadi
 * dibuat saat pertama kali disimpan.
 */
class UpdateCompanyProfileSettingsAction
{
    public function __construct(private readonly LogAuditAction $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(int $tenantId, array $data): CompanyProfileSetting
    {
        $existing = CompanyProfileSetting::query()->where('tenant_id', $tenantId)->first();
        $before = $existing?->getAttributes() ?? [];

        try {
            $settings = CompanyProfileSetting::query()->updateOrCreate(
                ['tenant_id' => $tenantId],
                $data,
            );
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan pengaturan company profile.', [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'data' => $data,
            ]);

            throw $e;
        }

        $after = $settings->getAttributes();
        $published = (bool) $settings->is_published;

        $this->audit->handle(
            action: 'company_settings.updated',
            subject: $settings,
            context: [
                'old' => array_intersect_key($before, $data),
                'new' => array_intersect_key($after, $data),
            ],
            description: $this->describe($before, $data, $published),
        );

        return $settings;
    }

    /**
     * Menerbitkan atau menyembunyikan halaman adalah perubahan yang paling
     * terasa oleh pengunjung, jadi disebut terpisah dari perubahan biasa.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $data
     */
    private function describe(array $before, array $data, bool $published): string
    {
        $wasPublished = (bool) ($before['is_published'] ?? false);

        if (array_key_exists('is_published', $data) && $wasPublished !== $published) {
            return $published
                ? 'Menerbitkan halaman company profile ke publik.'
                : 'Menyembunyikan halaman company profile dari publik.';
        }

        return 'Memperbarui pengaturan halaman company profile.';
    }
}

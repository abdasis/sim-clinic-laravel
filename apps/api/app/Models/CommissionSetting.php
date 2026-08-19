<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ClinicRole;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Setelan fee & komisi per klinik.
 *
 * Untuk sekarang isinya satu hal: peran mana yang berhak menerimanya. Kasir
 * belum ikut, tapi menyimpannya sebagai data membuat keputusan itu bisa
 * berubah lewat menu, bukan lewat rilis.
 */
#[ScopedBy([TenantScope::class])]
class CommissionSetting extends Model
{
    use BelongsToTenant;

    /** Keadaan yang berlaku bila klinik belum pernah menyetelnya. */
    public const DEFAULT_ROLES = [ClinicRole::Doctor->value, ClinicRole::Therapist->value];

    protected $fillable = ['tenant_id', 'eligible_roles'];

    protected function casts(): array
    {
        return ['eligible_roles' => 'array'];
    }

    /**
     * Peran yang berhak, untuk klinik yang sedang aktif.
     *
     * @return array<int, string>
     */
    public static function eligibleRoles(): array
    {
        $roles = static::query()->value('eligible_roles');

        return is_array($roles) && $roles !== [] ? $roles : self::DEFAULT_ROLES;
    }
}

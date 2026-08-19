<?php

namespace App\Console\Commands;

use App\Actions\Tenant\GrantMissingModulePermissionsAction;
use Illuminate\Console\Command;

/**
 * Lengkapi izin peran klinik yang tertinggal dari matriks bawaan.
 *
 * Dijalankan tiap deploy. Menambah modul di matriks tidak cukup: klinik yang
 * sudah berjalan izinnya dipasang sekali saat dibuat, dan yang membuat izin
 * modul baru selama ini hanya seeder — yang tidak pernah ikut deploy.
 *
 * Hanya menambah, tidak pernah mencabut, jadi aman dijalankan berulang dan
 * tidak membatalkan pengaturan izin yang sengaja dibuat admin klinik.
 */
class SyncClinicPermissionsCommand extends Command
{
    protected $signature = 'clinic:sync-permissions';

    protected $description = 'Lengkapi izin peran klinik yang tertinggal dari matriks bawaan';

    public function handle(GrantMissingModulePermissionsAction $action): int
    {
        $changed = $action->forAllTenants();

        if ($changed === []) {
            $this->info('Semua klinik sudah lengkap izinnya.');

            return self::SUCCESS;
        }

        foreach ($changed as $row) {
            $detail = collect($row['added'])
                ->map(fn (int $count, string $role) => "{$role} +{$count}")
                ->implode(', ');

            $this->info("{$row['tenant']->slug}: {$detail}");
        }

        return self::SUCCESS;
    }
}

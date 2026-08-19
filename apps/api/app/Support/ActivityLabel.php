<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Penerjemah nama teknis di audit log menjadi label yang enak dibaca.
 *
 * Nama modul menumpang `role.module_labels` supaya satu klinik memakai
 * sebutan yang sama di halaman izin peran dan di log — sisanya (login,
 * undangan, impor) dilengkapi lewat `activity_log.modules`.
 */
class ActivityLabel
{
    /**
     * Kolom yang paling mungkin menyimpan sebutan manusiawi sebuah baris,
     * berurutan dari yang paling spesifik.
     */
    private const NAME_COLUMNS = ['name', 'title', 'invoice_number', 'code', 'description', 'email'];

    public static function module(?string $logName): string
    {
        if ($logName === null || $logName === '') {
            return __('activity_log.modules.activity_log');
        }

        foreach (["activity_log.modules.{$logName}", "role.module_labels.{$logName}"] as $key) {
            $label = __($key);

            if ($label !== $key) {
                return $label;
            }
        }

        return Str::headline($logName);
    }

    /**
     * `expense.updated` -> "Diperbarui". Bagian modulnya dibuang karena
     * sudah tampil di kolom sendiri.
     */
    public static function event(?string $event): string
    {
        if ($event === null || $event === '') {
            return '-';
        }

        $verb = str_contains($event, '.') ? Str::afterLast($event, '.') : $event;
        $key = "activity_log.events.{$verb}";
        $label = __($key);

        return $label === $key ? Str::headline($verb) : $label;
    }

    /**
     * Jenis objek: App\Models\MedicalRecord -> "Rekam Medis".
     */
    public static function subjectType(?string $subjectType): ?string
    {
        if ($subjectType === null || $subjectType === '') {
            return null;
        }

        return self::module(Str::snake(class_basename($subjectType)));
    }

    /**
     * Sebutan satu baris objek. Model yang sudah terhapus tidak punya
     * atribut apa pun untuk dibaca, jadi pemanggil mengirim null dan
     * mendapat penanda "sudah dihapus" dari sisi Resource.
     */
    public static function subjectName(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (self::NAME_COLUMNS as $column) {
            $value = $subject->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 60);
            }
        }

        return '#'.$subject->getKey();
    }
}

<?php

namespace App\Support;

use App\Models\MembershipTier;

/**
 * Potongan keanggotaan untuk satu keranjang.
 *
 * Dihitung dari baris-barisnya, bukan dari total nota, karena tidak semua
 * baris berhak. Barang yang sedang promo bawaannya dilewati: di kebanyakan
 * klinik potongan member dan promo tidak digabung, dan menumpuknya diam-diam
 * berarti margin yang hilang tanpa ada yang pernah memutuskan. Tingkat yang
 * memang dimaksudkan menumpuk tinggal menyalakan `stacks_with_promo`.
 *
 * Kelas baca: tidak menyentuh basis data, tidak mengubah apa pun. Yang
 * menyimpan hasilnya TransactionService.
 */
class MemberDiscount
{
    private function __construct(
        public readonly ?string $tierName,
        public readonly float $base,
        public readonly float $amount,
    ) {}

    public static function none(): self
    {
        return new self(null, 0.0, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  baris hasil TransactionService
     */
    public static function for(?MembershipTier $tier, array $lines): self
    {
        if ($tier === null) {
            return self::none();
        }

        $base = 0.0;

        foreach ($lines as $line) {
            if (! $tier->stacks_with_promo && self::isOnPromo($line)) {
                continue;
            }

            $base += (float) $line['subtotal'];
        }

        if ($base <= 0) {
            // Tetap sebut nama tingkatnya. Member yang seluruh belanjanya
            // sedang promo berhak tahu keanggotaannya dikenali — nota tanpa
            // keterangan apa pun terbaca seperti kartunya tidak terpakai.
            return new self($tier->name, 0.0, 0.0);
        }

        $base = round($base, 2);
        $amount = round($base - $tier->applyTo($base), 2);

        return new self($tier->name, $base, $amount);
    }

    /**
     * Baris ini sedang menikmati promo?
     *
     * Dibandingkan dengan harga normalnya, bukan dengan ada-tidaknya promo di
     * basis data: yang menentukan apakah pasien sudah menerima potongan pada
     * baris itu adalah selisih harganya.
     *
     * @param  array<string, mixed>  $line
     */
    private static function isOnPromo(array $line): bool
    {
        return (float) $line['unit_price'] < (float) $line['list_price'];
    }
}

<?php

namespace App\Actions\Commission;

use App\Actions\LogAuditAction;
use App\Models\CommissionRule;
use BackedEnum;
use Illuminate\Support\Facades\Auth;

/**
 * Simpan aturan fee, baik baru maupun perubahan. Digabung karena bentuk
 * datanya identik dan bedanya hanya ada tidaknya baris lama.
 *
 * Mengubah tarif tidak menimpa baris lama. Baris lama ditutup pada detik
 * penyimpanan dan tarif barunya lahir sebagai baris tersendiri, sehingga
 * periode yang sudah lewat tetap dihitung dengan tarif yang berlaku waktu
 * itu. Admin tidak mengisi tanggal apa pun — kebijakan klinik berubah
 * mendadak, dan yang perlu dicatat cuma "sejak kapan yang baru berlaku".
 */
class SaveCommissionRuleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?CommissionRule $rule = null): CommissionRule
    {
        if ($rule === null) {
            return $this->create($data);
        }

        // Baris yang dibuat tanpa menyebut semua kolom belum memuat nilai
        // bawaannya; tanpa dibaca ulang, 0 di database terbaca null dan
        // sekadar ganti nama pun dikira perubahan tarif.
        $rule->refresh();

        if ($this->changesTheRate($rule, $data)) {
            return $this->supersede($rule, $data);
        }

        // Ganti nama atau jeda sementara: tidak mengubah uang yang dihitung,
        // jadi tidak perlu versi baru.
        $old = $rule->only(array_keys($data));

        $rule->update($data);

        app(LogAuditAction::class)->handle(
            'commission_rule.updated',
            $rule,
            Auth::user(),
            ['old' => $old, 'new' => $rule->only(array_keys($data))],
            'Memperbarui aturan fee '.$rule->name.'.',
        );

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(array $data): CommissionRule
    {
        $rule = CommissionRule::create($data + ['effective_from' => now()]);
        $rule->refresh();

        app(LogAuditAction::class)->handle(
            'commission_rule.created',
            $rule,
            Auth::user(),
            ['attributes' => $rule->getAttributes()],
            'Menambah aturan fee '.$rule->name.'.',
        );

        return $rule;
    }

    /**
     * Tutup tarif lama dan buka penggantinya pada saat yang sama persis,
     * supaya tidak ada celah waktu yang tidak punya tarif sama sekali.
     *
     * @param  array<string, mixed>  $data
     */
    private function supersede(CommissionRule $rule, array $data): CommissionRule
    {
        $at = now();
        $previous = $rule->getAttributes();

        $rule->forceFill(['effective_to' => $at])->save();

        $replacement = CommissionRule::create(
            $rule->only(['tenant_id', 'name', 'therapist_id', 'type', 'amount', 'percent', 'min_revenue', 'is_active'])
                + ['effective_from' => $at, 'effective_to' => null]
        );

        $replacement->forceFill($data)->save();
        $replacement->refresh();

        app(LogAuditAction::class)->handle(
            'commission_rule.superseded',
            $replacement,
            Auth::user(),
            ['old' => $previous, 'new' => $replacement->getAttributes()],
            'Mengubah tarif aturan fee '.$replacement->name.'; tarif lama berlaku sampai '
                .$at->translatedFormat('d F Y H:i').'.',
        );

        return $replacement;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changesTheRate(CommissionRule $rule, array $data): bool
    {
        foreach (CommissionRule::RATE_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            // Dibandingkan sebagai angka: '5000' dan '5000.00' tarif yang sama,
            // dan versi baru yang lahir tanpa perubahan nilai cuma jadi sampah.
            // Enum ikut diratakan ke nilainya -- model mengembalikan objek
            // sedangkan request mengirim string, dan keduanya tarif yang sama.
            $before = $this->comparable($rule->getAttribute($field));
            $after = $this->comparable($data[$field]);

            $same = is_numeric($before) && is_numeric($after)
                ? (float) $before === (float) $after
                : $before === $after;

            if (! $same) {
                return true;
            }
        }

        return false;
    }

    private function comparable(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}

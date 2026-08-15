<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return __('clinic.discount_type.'.$this->value);
    }

    /**
     * Hitung harga setelah potongan. Hasilnya tidak pernah negatif — potongan
     * tetap yang melebihi harga membuat barisnya gratis, bukan berbayar minus.
     */
    public function apply(float $price, float $value): float
    {
        $discounted = match ($this) {
            self::Percent => $price - ($price * $value / 100),
            self::Fixed => $price - $value,
        };

        return max(0.0, round($discounted, 2));
    }
}

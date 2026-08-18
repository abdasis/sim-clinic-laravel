<?php

namespace App\Enums;

/**
 * Jenis entitas yang boleh dikategorikan.
 *
 * Kategori disimpan di satu tabel untuk ketiganya, jadi jenisnya perlu ikut
 * tercatat — tanpa itu kategori layanan bisa menempel ke produk.
 */
enum CategorizableType: string
{
    case Service = 'service';
    case Product = 'product';
    case Expense = 'expense';

    public function label(): string
    {
        return __('category.type_'.$this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Alias morph map untuk jenis ini. */
    public function morphAlias(): string
    {
        return $this->value;
    }
}

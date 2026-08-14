<?php

namespace Tests\Unit\Product;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class IsLowStockTest extends TestCase
{
    public function test_balance_below_threshold_is_low(): void
    {
        $this->assertTrue($this->product(3, 5)->is_low_stock);
    }

    public function test_balance_equal_to_threshold_is_low(): void
    {
        $this->assertTrue(
            $this->product(5, 5)->is_low_stock,
            'Saldo tepat di ambang harus dianggap menipis (FR-065).',
        );
    }

    public function test_balance_above_threshold_is_not_low(): void
    {
        $this->assertFalse($this->product(6, 5)->is_low_stock);
    }

    public function test_zero_threshold_and_zero_balance_is_low(): void
    {
        $this->assertTrue($this->product(0, 0)->is_low_stock);
    }

    private function product(int $balance, int $threshold): Product
    {
        $product = new Product;
        $product->stock_balance = $balance;
        $product->min_threshold = $threshold;

        return $product;
    }
}

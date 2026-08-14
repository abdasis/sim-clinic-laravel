<?php

namespace App\Services;

use App\Actions\Product\ArchiveProductAction;
use App\Actions\Product\CreateProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Models\Product;

/**
 * Use case master produk klinik.
 */
class ProductService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product
    {
        return app(CreateProductAction::class)->handle($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        return app(UpdateProductAction::class)->handle($product, $attributes);
    }

    public function archive(Product $product): Product
    {
        return app(ArchiveProductAction::class)->handle($product);
    }
}

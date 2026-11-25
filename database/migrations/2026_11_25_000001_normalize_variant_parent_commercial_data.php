<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move legacy parent-level commercial values onto variants before enforcing
     * the invariant that a variant product parent has no price or stock unit.
     */
    public function up(): void
    {
        $variantProducts = DB::table('products')
            ->where('product_type', 'variant')
            ->whereIn('id', DB::table('product_variants')->select('product_id'))
            ->orderBy('id')
            ->get(['id', 'price', 'unit_id']);

        foreach ($variantProducts as $product) {
            $variantQuery = DB::table('product_variants')
                ->where('product_id', $product->id);

            if ($product->price !== null) {
                (clone $variantQuery)
                    ->whereNull('price')
                    ->update(['price' => $product->price]);
            }

            if ($product->unit_id !== null) {
                (clone $variantQuery)
                    ->whereNull('unit_id')
                    ->update(['unit_id' => $product->unit_id]);
            }
        }

        DB::table('products')
            ->where('product_type', 'variant')
            ->whereIn('id', DB::table('product_variants')->select('product_id'))
            ->update([
                'price' => null,
                'unit_id' => null,
            ]);
    }

    public function down(): void
    {
        // Commercial values are intentionally not moved back to the parent:
        // variants may now have distinct prices and units.
    }
};

<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeProducts extends Command
{
    protected $signature = 'app:initialize-products';

    protected $description = 'Create missing diesel and petrol catalog entries without changing existing products';

    public function handle(): int
    {
        try {
            DB::transaction(function (): void {
                DB::table('operation_locks')->insertOrIgnore(['name' => 'initialize-products']);
                DB::table('operation_locks')->where('name', 'initialize-products')->lockForUpdate()->first();
                $products = Product::withTrashed()->get();
                foreach (['DSL' => 'دیزل', 'PTR' => 'پترول'] as $code => $name) {
                    $matches = $products->filter(fn (Product $product) => strtoupper(trim($product->code)) === $code
                        || Product::fuelCodeForName($product->name) === $code);
                    if ($matches->isEmpty()) {
                        Product::create(['code' => $code, 'name' => $name, 'unit' => 'kg', 'is_active' => true]);

                        continue;
                    }
                    $product = $matches->first();
                    $namedCode = Product::fuelCodeForName($product->name);
                    if ($matches->count() !== 1 || $product->trashed() || ! $product->is_active
                        || ($namedCode !== null && in_array(strtoupper(trim($product->code)), ['DSL', 'PTR'], true)
                            && strtoupper(trim($product->code)) !== $namedCode)) {
                        throw new RuntimeException("Review {$code} in the product catalog: inactive, archived, multiple, or conflicting matches. No products changed.");
                    }
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Diesel and petrol are available.');

        return self::SUCCESS;
    }
}

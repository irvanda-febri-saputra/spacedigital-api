<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\ProductVariant;

class MigrateVariantsToTable extends Command
{
    protected $signature = 'variants:migrate';
    protected $description = 'Migrate product variants from JSON column to product_variants table';

    public function handle()
    {
        $this->info('Starting variant migration...');

        $products = Product::whereNotNull('variants')->get();
        $migrated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $variants = $product->variants;
            
            if (!is_array($variants) || empty($variants)) {
                continue;
            }

            $this->info("Processing: {$product->name} ({$product->id})");

            foreach ($variants as $index => $variant) {
                // Check if already exists
                $existing = ProductVariant::where('product_id', $product->id)
                    ->where(function ($query) use ($variant) {
                        $query->where('variant_code', $variant['variant_code'] ?? null)
                              ->orWhere('name', $variant['name'] ?? '');
                    })
                    ->first();

                if ($existing) {
                    $this->line("  - Skipped (exists): {$variant['name']}");
                    $skipped++;
                    continue;
                }

                // Create new variant
                ProductVariant::create([
                    'product_id' => $product->id,
                    'variant_code' => $variant['variant_code'] ?? strtoupper(str_replace(' ', '_', $product->product_code . '_' . ($variant['name'] ?? 'VAR' . $index))),
                    'name' => $variant['name'] ?? 'Variant ' . ($index + 1),
                    'price' => $variant['price'] ?? $product->price ?? 0,
                    'description' => $variant['description'] ?? null,
                    'is_active' => true,
                    'sort_order' => $index,
                ]);

                $this->line("  + Migrated: {$variant['name']}");
                $migrated++;
            }
        }

        $this->newLine();
        $this->info("Migration complete!");
        $this->info("Migrated: {$migrated} variants");
        $this->info("Skipped: {$skipped} variants (already exist)");

        return 0;
    }
}

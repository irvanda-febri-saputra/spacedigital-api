<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDuplicateProducts extends Command
{
    protected $signature = 'products:clean-duplicates {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Remove duplicate products (same name, same bot_id) keeping the one with product_code';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('Finding duplicate products...');

        // Find duplicates grouped by bot_id and lowercase name
        $duplicates = DB::select("
            SELECT bot_id, LOWER(name) as name_lower, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
            FROM products
            GROUP BY bot_id, LOWER(name)
            HAVING COUNT(*) > 1
        ");

        if (empty($duplicates)) {
            $this->info('No duplicates found!');
            return 0;
        }

        $this->info("Found " . count($duplicates) . " duplicate groups");

        $deletedCount = 0;

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup->ids);

            // Get all products in this duplicate group
            $products = Product::whereIn('id', $ids)->get();

            $this->line("\n📦 Duplicate group: '{$dup->name_lower}' (Bot ID: {$dup->bot_id})");

            // Find the "best" one to keep (prefer one with product_code)
            $keep = null;
            $toDelete = [];

            foreach ($products as $product) {
                $this->line("  - ID: {$product->id} | Code: " . ($product->product_code ?: 'NULL') . " | Name: {$product->name}");

                if (!$keep) {
                    $keep = $product;
                } else {
                    // Prefer product with product_code
                    if ($product->product_code && !$keep->product_code) {
                        $toDelete[] = $keep;
                        $keep = $product;
                    } else {
                        $toDelete[] = $product;
                    }
                }
            }

            $this->info("  ✅ KEEP: ID {$keep->id} ({$keep->name})");

            foreach ($toDelete as $product) {
                $this->warn("  ❌ DELETE: ID {$product->id} ({$product->name})");

                if (!$dryRun) {
                    // Move any stock items to the kept product
                    $stockMoved = DB::table('stock_items')
                        ->where('product_id', $product->id)
                        ->update(['product_id' => $keep->id]);

                    if ($stockMoved > 0) {
                        $this->line("     → Moved {$stockMoved} stock items to kept product");
                    }

                    // Move variants if any
                    DB::table('product_variants')
                        ->where('product_id', $product->id)
                        ->update(['product_id' => $keep->id]);

                    // Delete the duplicate
                    $product->delete();
                    $deletedCount++;
                }
            }
        }

        if ($dryRun) {
            $this->warn("\n[DRY RUN] Would have deleted " . count($toDelete) . " duplicate products");
            $this->info("Run without --dry-run to actually delete them");
        } else {
            $this->info("\n✅ Deleted {$deletedCount} duplicate products");
        }

        return 0;
    }
}

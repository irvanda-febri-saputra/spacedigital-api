<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Bot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProductController extends Controller
{
    // ============================================================
    // API METHODS (for SPA Dashboard)
    // ============================================================

    /**
     * API: Get all products for user's bots
     */
    public function apiIndex(Request $request)
    {
        $user = $request->user();

        // Get user's bot IDs
        if ($user->isSuperAdmin()) {
            $botIds = Bot::pluck('id');
        } else {
            $botIds = $user->bots()->pluck('id');
        }

        $query = Product::whereIn('bot_id', $botIds)->with(['bot:id,name', 'productVariants']);

        // Filters
        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->bot_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->orderBy('sort_order')->orderBy('name')
            ->paginate($request->get('per_page', 20))
            ->through(function ($product) {
                // Calculate REAL stock count from stock_items table
                $realStockCount = \App\Models\StockItem::where('product_id', $product->id)
                    ->where('is_sold', false)
                    ->count();
                
                return [
                    'id' => $product->id,
                    'bot_id' => $product->bot_id,
                    'bot' => $product->bot ? ['id' => $product->bot->id, 'name' => $product->bot->name] : null,
                    'product_code' => $product->product_code,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock' => $realStockCount,
                    'stock_count' => $realStockCount,
                    'sold_count' => $product->sold_count ?? 0,
                    'category' => $product->category,
                    // Use productVariants relationship if available, fallback to JSON column for old data
                    'variants' => $product->productVariants->count() > 0
                        ? $product->productVariants->map(function($v) {
                            // Calculate real stock for each variant
                            $variantStock = \App\Models\StockItem::where('variant_id', $v->id)
                                ->where('is_sold', false)
                                ->count();
                            return [
                                'id' => $v->id,
                                'variant_code' => $v->variant_code,
                                'name' => $v->name,
                                'price' => $v->price,
                                'stock_count' => $variantStock,
                            ];
                        })->toArray()
                        : (is_string($product->variants) ? json_decode($product->variants, true) : ($product->variants ?? [])),
                    'image_url' => $product->image_url,
                    'is_active' => $product->is_active,
                    'sort_order' => $product->sort_order,
                    'created_at' => $product->created_at->toIso8601String(),
                ];
            });

        return response()->json($products);
    }

    /**
     * API: Get single product
     */
    public function apiShow(Request $request, Product $product)
    {
        $user = $request->user();

        // Check ownership
        if (!$user->isSuperAdmin() && $product->bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $product->id,
            'bot_id' => $product->bot_id,
            'bot' => $product->bot ? ['id' => $product->bot->id, 'name' => $product->bot->name] : null,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'category' => $product->category,
            'variants' => $product->variants,
            'image_url' => $product->image_url,
            'is_active' => $product->is_active,
            'sort_order' => $product->sort_order,
        ]);
    }

    /**
     * API: Create product
     */
    public function apiStore(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'bot_id' => 'nullable|exists:bots,id',
            'product_code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'integer|min:-1',
            'category' => 'nullable|string|max:100',
            'variants' => 'nullable|array',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Auto-select first bot if not provided
        $botId = $validated['bot_id'] ?? null;
        if (!$botId) {
            $firstBot = $user->bots()->first();
            if (!$firstBot) {
                return response()->json(['error' => 'No bot found. Please create a bot first.'], 400);
            }
            $botId = $firstBot->id;
        }

        // Check bot ownership
        $bot = Bot::findOrFail($botId);
        if (!$user->isSuperAdmin() && $bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized - not your bot'], 403);
        }

        // Check for duplicate product name in same bot (CASE-INSENSITIVE)
        $existingProduct = Product::where('bot_id', $botId)
            ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
            ->first();

        if ($existingProduct) {
            // Return existing product instead of creating duplicate
            Log::info("Product '{$validated['name']}' already exists (ID: {$existingProduct->id})");
            return response()->json([
                'success' => true,
                'message' => 'Product already exists',
                'data' => $existingProduct,
                'id' => $existingProduct->id,
            ], 200);
        }

        $product = Product::create([
            'bot_id' => $botId,
            'product_code' => $validated['product_code'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'] ?? 0,
            'stock' => $validated['stock'] ?? -1,
            'category' => $validated['category'] ?? null,
            'variants' => $validated['variants'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        // Broadcast new product to bot via WebSocket
        try {
            $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
            $wsSecret = env('WS_BROADCAST_SECRET');

            // Parse variants if it's JSON string
            $variants = [];
            if ($product->variants) {
                $variants = is_string($product->variants)
                    ? json_decode($product->variants, true)
                    : $product->variants;
            }

            $response = Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'product.created',
                'data' => [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'category' => $product->category,
                    'is_active' => $product->is_active,
                    'variants' => $variants,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info("Product creation broadcasted to {$result['clients']} bot(s) for product {$product->id}");
            } else {
                Log::warning("WebSocket broadcast failed for new product {$product->id}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast product creation: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * API: Update product
     */
    public function apiUpdate(Request $request, Product $product)
    {
        $user = $request->user();

        // Check ownership
        if (!$user->isSuperAdmin() && $product->bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_code' => 'nullable|string|max:50',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:-1',
            'category' => 'nullable|string|max:100',
            'variants' => 'nullable|array',
            'image_url' => 'nullable|url|max:500',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $product->update($validated);

        // Broadcast product update via WebSocket (includes variants)
        try {
            $bot = $product->bot;
            $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
            $wsSecret = env('WS_BROADCAST_SECRET');

            // Parse variants if it's JSON string
            $variants = [];
            if ($product->variants) {
                $variants = is_string($product->variants)
                    ? json_decode($product->variants, true)
                    : $product->variants;
            }

            $response = Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'product.updated',
                'data' => [
                    'product_id' => $product->bot_external_id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'category' => $product->category,
                    'is_active' => $product->is_active,
                    'variants' => $variants,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info("Product update broadcasted to {$result['clients']} bot(s) for product {$product->id}");
            } else {
                Log::warning("WebSocket broadcast failed for product {$product->id}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast product update: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * API: Add stock to product (will notify bot via webhook)
     */
    public function addStock(Request $request, Product $product)
    {
        $user = $request->user();

        // Check ownership
        if (!$user->isSuperAdmin() && $product->bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        \Log::info("AddStock Request for Product {$product->id}:", $request->all());

        $validated = $request->validate([
            'variant_id' => 'nullable', // Loose type validation to handle empty strings
            'variant_code' => 'nullable|string',
            'variant_name' => 'nullable|string',
            'stock_data' => 'required|string'
        ]);

        // Resolve variant code if only ID provided
        $variantCode = $validated['variant_code'] ?? null;

        // If code matches empty string, set to null
        if ($variantCode === '') $variantCode = null;

        if (empty($variantCode) && !empty($validated['variant_id'])) {
             // Parse variants from product (could be JSON string or array)
             $variants = is_string($product->variants) ? json_decode($product->variants, true) : $product->variants;

             if (is_array($variants)) {
                 foreach ($variants as $v) {
                     // Robust comparison: ID, Name, or Code
                     $input = trim(strtolower($validated['variant_id'] ?? ''));
                     $vId = trim(strtolower($v['id'] ?? ''));
                     $vName = trim(strtolower($v['name'] ?? ''));
                     $vCode = trim(strtolower($v['variant_code'] ?? ''));

                     if ($input === $vId || $input === $vName || $input === $vCode) {
                         $variantCode = $v['variant_code'] ?? null;
                         // fallback to name if code missing
                         if (!$variantCode) $variantCode = $v['name'] ?? null;
                         break;
                     }
                 }
             }
        }

        \Log::info("Resolved Variant Code: " . ($variantCode ?? 'NULL'));

    // Broadcast stock add via WebSocket (real-time to all bots)
    try {
        $bot = $product->bot;

        // Broadcast via WebSocket Hub
        $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
        $wsSecret = env('WS_BROADCAST_SECRET');

        $response = Http::timeout(5)->post("{$wsUrl}/broadcast", [
            'secret' => $wsSecret,
            'channel' => "bot.{$bot->id}",
            'event' => 'product.stock_added',
            'data' => [
                'product_name' => $product->name, // Send name instead of ID
                'variant_code' => $variantCode, // Send resolved variant code
                'stock_data' => $validated['stock_data'],
                'timestamp' => now()->toIso8601String()
            ]
        ]);

        if ($response->successful()) {
            $result = $response->json();
            Log::info("Stock add broadcasted to {$result['clients']} bot(s) for product {$product->id}");

            // Count stock items added - normalize line endings (browser sends \r\n)
            $normalizedData = str_replace("\r\n", "\n", $validated['stock_data']);
            $stockLines = array_filter(explode("\n", $normalizedData));
            $stockCount = count($stockLines);

            // Update stock_count in Laravel for immediate dashboard refresh
            $product->increment('stock', $stockCount);

            return response()->json([
                'success' => true,
                'message' => 'Stock ditambahkan! Bot akan sync dalam beberapa detik.',
                'broadcast_sent' => true,
                'clients_notified' => $result['clients'] ?? 0,
                'stock_added' => $stockCount
            ]);
        } else {
            Log::warning("WebSocket broadcast failed for product {$product->id}: " . $response->body());
        }
    } catch (\Exception $e) {
        Log::warning("Failed to broadcast stock add: " . $e->getMessage());
    }

    // Fallback response if broadcast fails
    return response()->json([
        'success' => true,
        'message' => 'Stock request diterima. Bot akan sync otomatis.',
        'broadcast_sent' => false
    ]);}

    /**
     * API: Delete product
     */
    public function apiDestroy(Request $request, Product $product)
    {
        $user = $request->user();

        // Check ownership
        if (!$user->isSuperAdmin() && $product->bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $bot = $product->bot;
        $productId = $product->bot_external_id;

        // Delete product
        $product->delete();

        // Broadcast delete via WebSocket
        try {
            $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
            $wsSecret = env('WS_BROADCAST_SECRET');

            $response = Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'product.deleted',
                'data' => [
                    'product_id' => $productId,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info("Product delete broadcasted to {$result['clients']} bot(s)");
            } else {
                Log::warning("WebSocket broadcast failed for product delete: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast product delete: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * API: Get categories for user's products
     */
    public function apiCategories(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $botIds = Bot::pluck('id');
        } else {
            $botIds = $user->bots()->pluck('id');
        }

        $categories = Product::whereIn('bot_id', $botIds)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return response()->json($categories);
    }

    /**
     * API: Bulk update products (for reordering)
     */
    public function apiBulkUpdate(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['products'] as $item) {
            $product = Product::find($item['id']);

            // Check ownership
            if (!$user->isSuperAdmin() && $product->bot->user_id !== $user->id) {
                continue;
            }

            $product->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Products reordered successfully',
        ]);
    }

    /**
     * Bot API: Sync single product (stock count update from bot)
     */
    public function syncSingle(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string',
            'stock_count' => 'required|integer|min:0',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer',
            'variants.*.variant_code' => 'nullable|string',
            'variants.*.stock_count' => 'required|integer|min:0',
        ]);

        try {
            // Find product by bot's internal ID is not reliable
            // Find by name instead (unique per bot)
            $botId = $request->header('X-Bot-Id'); // Assuming bot sends its ID

            if (!$botId) {
                // Fallback: find by name only (assuming unique names)
                $product = Product::where('name', $validated['name'])->first();
            } else {
                $product = Product::where('bot_id', $botId)
                    ->where('name', $validated['name'])
                    ->first();
            }

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product '{$validated['name']}' not found in dashboard"
                ], 404);
            }

            // Update stock count
            $product->update(['stock' => $validated['stock_count']]);

            Log::info("Product stock synced from bot: {$product->name} = {$validated['stock_count']}");

            return response()->json([
                'success' => true,
                'message' => 'Product stock updated successfully',
                'product_id' => $product->id,
                'stock' => $product->stock
            ]);

        } catch (\Exception $e) {
            Log::error("Bot sync-single error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

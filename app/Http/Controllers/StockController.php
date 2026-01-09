<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class StockController extends Controller
{
    /**
     * List stock items with filters
     */
    public function apiIndex(Request $request)
    {
        $user = $request->user();
        
        // Get user's bot IDs
        if ($user->isSuperAdmin()) {
            $productIds = Product::pluck('id');
        } else {
            $botIds = $user->bots()->pluck('id');
            $productIds = Product::whereIn('bot_id', $botIds)->pluck('id');
        }

        $query = StockItem::whereIn('product_id', $productIds)
            ->with(['product:id,name,product_code', 'variant:id,name,variant_code,price']);

        // Filters
        if ($request->has('product_id') && $request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('variant_id') && $request->variant_id) {
            $query->where('variant_id', $request->variant_id);
        }

        if ($request->has('is_sold')) {
            $query->where('is_sold', $request->boolean('is_sold'));
        }

        if ($request->has('search') && $request->search) {
            $query->where('data', 'like', '%' . $request->search . '%');
        }

        $stocks = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($stocks);
    }

    /**
     * Add stock items
     */
    public function apiStore(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'data' => 'required|string', // Can be multiline for bulk
        ]);

        // Check ownership
        $product = Product::findOrFail($validated['product_id']);
        if (!$user->isSuperAdmin()) {
            $botIds = $user->bots()->pluck('id')->toArray();
            if (!in_array($product->bot_id, $botIds)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        // Parse multiline data
        $lines = array_filter(
            array_map('trim', explode("\n", str_replace("\r\n", "\n", $validated['data']))),
            fn($line) => !empty($line)
        );

        $createdItems = [];
        foreach ($lines as $line) {
            $item = StockItem::create([
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'data' => $line,
                'is_sold' => false,
            ]);
            $createdItems[] = $item;
        }

        // Broadcast to bot via WebSocket
        $this->broadcastStockAdded($product, $validated['variant_id'] ?? null, $createdItems);

        Log::info("Stock added: {$product->name} - " . count($createdItems) . " items");

        return response()->json([
            'success' => true,
            'message' => count($createdItems) . ' stock items added',
            'count' => count($createdItems),
            'items' => $createdItems,
        ], 201);
    }

    /**
     * Update stock item
     */
    public function apiUpdate(Request $request, StockItem $stock)
    {
        $user = $request->user();

        // Check ownership
        $product = $stock->product;
        if (!$user->isSuperAdmin()) {
            $botIds = $user->bots()->pluck('id')->toArray();
            if (!in_array($product->bot_id, $botIds)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $validated = $request->validate([
            'data' => 'sometimes|string',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $stock->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stock updated',
            'data' => $stock->fresh(['product', 'variant']),
        ]);
    }

    /**
     * Delete stock item
     */
    public function apiDestroy(Request $request, StockItem $stock)
    {
        $user = $request->user();

        // Check ownership
        $product = $stock->product;
        if (!$user->isSuperAdmin()) {
            $botIds = $user->bots()->pluck('id')->toArray();
            if (!in_array($product->bot_id, $botIds)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $stock->delete();

        // Broadcast to bot
        $this->broadcastStockDeleted($product, $stock->id);

        return response()->json([
            'success' => true,
            'message' => 'Stock deleted',
        ]);
    }

    /**
     * Bulk import stocks
     */
    public function apiBulkImport(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'items' => 'required|array|min:1',
            'items.*' => 'required|string',
        ]);

        // Check ownership
        $product = Product::findOrFail($validated['product_id']);
        if (!$user->isSuperAdmin()) {
            $botIds = $user->bots()->pluck('id')->toArray();
            if (!in_array($product->bot_id, $botIds)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $createdItems = [];
        foreach ($validated['items'] as $data) {
            $item = StockItem::create([
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'data' => trim($data),
                'is_sold' => false,
            ]);
            $createdItems[] = $item;
        }

        // Broadcast to bot
        $this->broadcastStockAdded($product, $validated['variant_id'] ?? null, $createdItems);

        return response()->json([
            'success' => true,
            'message' => count($createdItems) . ' items imported',
            'count' => count($createdItems),
        ], 201);
    }

    /**
     * Get stock statistics
     */
    public function apiStats(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $productIds = Product::pluck('id');
        } else {
            $botIds = $user->bots()->pluck('id');
            $productIds = Product::whereIn('bot_id', $botIds)->pluck('id');
        }

        $stats = [
            'total' => StockItem::whereIn('product_id', $productIds)->count(),
            'available' => StockItem::whereIn('product_id', $productIds)->where('is_sold', false)->count(),
            'sold' => StockItem::whereIn('product_id', $productIds)->where('is_sold', true)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Broadcast stock added to bot via WebSocket
     */
    private function broadcastStockAdded(Product $product, $variantId, array $items)
    {
        try {
            $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
            $wsSecret = env('WS_BROADCAST_SECRET');
            $bot = $product->bot;

            $variant = $variantId ? ProductVariant::find($variantId) : null;

            Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'stock.added',
                'data' => [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $variantId,
                    'variant_code' => $variant?->variant_code,
                    'variant_name' => $variant?->name,
                    'items' => array_map(fn($item) => [
                        'id' => $item->id,
                        'data' => $item->data,
                    ], $items),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast stock add: " . $e->getMessage());
        }
    }

    /**
     * Broadcast stock deleted to bot via WebSocket
     */
    private function broadcastStockDeleted(Product $product, $stockId)
    {
        try {
            $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
            $wsSecret = env('WS_BROADCAST_SECRET');
            $bot = $product->bot;

            Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'stock.deleted',
                'data' => [
                    'stock_id' => $stockId,
                    'product_id' => $product->id,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast stock delete: " . $e->getMessage());
        }
    }
}

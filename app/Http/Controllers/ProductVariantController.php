<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ProductVariantController extends Controller
{
    /**
     * Get variants for a product
     */
    public function apiIndex(Request $request, Product $product)
    {
        $user = $request->user();

        // Check ownership (semua user hanya bisa lihat variant dari bot miliknya)
        $botIds = $user->bots()->pluck('id')->toArray();
        if (!in_array($product->bot_id, $botIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $variants = $product->productVariants()
            ->withCount(['stockItems as available_stock' => function ($query) {
                $query->where('is_sold', false);
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $variants,
        ]);
    }

    /**
     * Create variant for a product
     */
    public function apiStore(Request $request, Product $product)
    {
        $user = $request->user();

        // Check ownership (semua user hanya bisa buat variant untuk bot miliknya)
        $botIds = $user->bots()->pluck('id')->toArray();
        if (!in_array($product->bot_id, $botIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'variant_code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Auto-generate variant_code if not provided
        if (empty($validated['variant_code'])) {
            $validated['variant_code'] = strtoupper(
                str_replace(' ', '_', $product->product_code . '_' . $validated['name'])
            );
        }

        $variant = $product->productVariants()->create($validated);

        // Broadcast to bot
        $this->broadcastVariantCreated($product, $variant);

        Log::info("Variant created: {$product->name} - {$variant->name}");

        return response()->json([
            'success' => true,
            'message' => 'Variant created',
            'data' => $variant,
        ], 201);
    }

    /**
     * Update variant
     */
    public function apiUpdate(Request $request, ProductVariant $variant)
    {
        $user = $request->user();
        $product = $variant->product;

        // Check ownership (semua user hanya bisa update variant dari bot miliknya)
        $botIds = $user->bots()->pluck('id')->toArray();
        if (!in_array($product->bot_id, $botIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'variant_code' => 'nullable|string|max:50',
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $variant->update($validated);

        // Broadcast to bot
        $this->broadcastVariantUpdated($product, $variant);

        return response()->json([
            'success' => true,
            'message' => 'Variant updated',
            'data' => $variant->fresh(),
        ]);
    }

    /**
     * Delete variant
     */
    public function apiDestroy(Request $request, ProductVariant $variant)
    {
        $user = $request->user();
        $product = $variant->product;

        // Check ownership (semua user hanya bisa hapus variant dari bot miliknya)
        $botIds = $user->bots()->pluck('id')->toArray();
        if (!in_array($product->bot_id, $botIds)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $variantId = $variant->id;
        $variant->delete();

        // Broadcast to bot
        $this->broadcastVariantDeleted($product, $variantId);

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted',
        ]);
    }

    /**
     * Broadcast variant created
     */
    private function broadcastVariantCreated(Product $product, ProductVariant $variant)
    {
        $this->broadcast($product, 'variant.created', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variant' => [
                'id' => $variant->id,
                'variant_code' => $variant->variant_code,
                'name' => $variant->name,
                'price' => $variant->price,
            ],
        ]);
    }

    /**
     * Broadcast variant updated
     */
    private function broadcastVariantUpdated(Product $product, ProductVariant $variant)
    {
        $this->broadcast($product, 'variant.updated', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variant' => [
                'id' => $variant->id,
                'variant_code' => $variant->variant_code,
                'name' => $variant->name,
                'price' => $variant->price,
                'is_active' => $variant->is_active,
            ],
        ]);
    }

    /**
     * Broadcast variant deleted
     */
    private function broadcastVariantDeleted(Product $product, $variantId)
    {
        $this->broadcast($product, 'variant.deleted', [
            'product_id' => $product->id,
            'variant_id' => $variantId,
        ]);
    }

    /**
     * Generic broadcast helper
     */
    private function broadcast(Product $product, string $event, array $data)
    {
        try {
            $wsUrl = env('WS_HUB_URL', 'http://localhost:8080');
            $wsSecret = env('WS_BROADCAST_SECRET');
            $bot = $product->bot;

            Http::timeout(5)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => $event,
                'data' => array_merge($data, ['timestamp' => now()->toIso8601String()]),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast {$event}: " . $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Bot;
use Illuminate\Http\Request;
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

        $query = Product::whereIn('bot_id', $botIds)->with('bot:id,name');

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
            ->through(fn ($product) => [
                'id' => $product->id,
                'bot_id' => $product->bot_id,
                'bot' => $product->bot ? ['id' => $product->bot->id, 'name' => $product->bot->name] : null,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'stock_count' => $product->stock_count ?? 0,
                'category' => $product->category,
                'variants' => is_string($product->variants) ? json_decode($product->variants, true) : ($product->variants ?? []),
                'image_url' => $product->image_url,
                'is_active' => $product->is_active,
                'sort_order' => $product->sort_order,
                'created_at' => $product->created_at->toIso8601String(),
            ]);

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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
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

        $product = Product::create([
            'bot_id' => $botId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'] ?? -1,
            'category' => $validated['category'] ?? null,
            'variants' => $validated['variants'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

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

        // Notify bot of product update (two-way sync)
        try {
            $bot = $product->bot;
            if ($bot && $bot->webhook_url) {
                $webhookUrl = rtrim($bot->webhook_url, '/') . '/webhook/product-update';

                Http::timeout(5)->post($webhookUrl, [
                    'product_id' => $product->bot_external_id,
                    'action' => 'update',
                    'data' => [
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $product->price,
                        'category' => $product->category,
                        'is_active' => $product->is_active,
                    ]
                ]);

                Log::info("Notified bot of product update: {$product->id}");
            }
        } catch (\Exception $e) {
            Log::warning("Failed to notify bot: " . $e->getMessage());
            // Don't fail the request if webhook fails
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

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

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
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
}

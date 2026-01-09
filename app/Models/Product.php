<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'bot_external_id',
        'product_code',
        'name',
        'description',
        'price',
        'stock',
        'stock_count',
        'category',
        'variants',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'variants' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the bot that owns the product
     */
    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Scope active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by bot
     */
    public function scopeForBot($query, $botId)
    {
        return $query->where('bot_id', $botId);
    }

    /**
     * Get product variants (new separate table)
     */
    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /**
     * Get stock items for this product
     */
    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }

    /**
     * Get available (unsold) stock count
     */
    public function getAvailableStockCountAttribute()
    {
        return $this->stockItems()->where('is_sold', false)->count();
    }

    /**
     * Get variants with stock counts (for API response)
     */
    public function getVariantsWithStockAttribute()
    {
        return $this->productVariants()->active()->get()->map(function ($variant) {
            return [
                'id' => $variant->id,
                'variant_code' => $variant->variant_code,
                'name' => $variant->name,
                'price' => $variant->price,
                'stock_count' => $variant->available_stock,
            ];
        });
    }
}

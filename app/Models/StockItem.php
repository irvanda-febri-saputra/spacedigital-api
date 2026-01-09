<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'data',
        'is_sold',
        'sold_at',
        'sold_to_telegram_id',
        'sold_order_id',
    ];

    protected $casts = [
        'is_sold' => 'boolean',
        'sold_at' => 'datetime',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant (nullable)
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Scope available (unsold) items
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_sold', false);
    }

    /**
     * Scope sold items
     */
    public function scopeSold($query)
    {
        return $query->where('is_sold', true);
    }

    /**
     * Mark as sold
     */
    public function markAsSold($telegramId = null, $orderId = null)
    {
        $this->update([
            'is_sold' => true,
            'sold_at' => now(),
            'sold_to_telegram_id' => $telegramId,
            'sold_order_id' => $orderId,
        ]);
    }
}

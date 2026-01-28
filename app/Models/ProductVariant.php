<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_code',
        'name',
        'price',
        'description',
        'terms',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the product that owns the variant
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get stock items for this variant
     */
    public function stockItems()
    {
        return $this->hasMany(StockItem::class, 'variant_id');
    }

    /**
     * Get available (unsold) stock count
     */
    public function getAvailableStockAttribute()
    {
        return $this->stockItems()->where('is_sold', false)->count();
    }

    /**
     * Scope active variants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'name',
        'description',
        'price',
        'stock',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'order_id',
        'telegram_user_id',
        'telegram_username',
        'product_name',
        'variant',
        'quantity',
        'price',
        'total_price',
        'payment_gateway',
        'payment_ref',
        'qiospay_trx_id', // QiosPay mutation ID to prevent double matching
        'orderkuota_trx_id', // OrderKuota mutation ID to prevent double matching
        'status',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    /**
     * Get the bot that owns this transaction
     */
    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * Scope for successful transactions
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}

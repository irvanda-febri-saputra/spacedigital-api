<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'bot_token',
        'bot_username',
        'payment_gateway',
        'pg_merchant_code',
        'pg_api_key',
        'pg_qr_string',
        'status',
        'settings',
        'active_gateway_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $hidden = [
        'bot_token',
        'pg_api_key',
    ];

    /**
     * Get the user that owns the bot
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the active payment gateway configuration
     */
    public function activeGateway()
    {
        return $this->belongsTo(UserGateway::class, 'active_gateway_id');
    }

    /**
     * Get transactions for this bot
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if bot is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get masked token for display
     */
    public function getMaskedTokenAttribute(): string
    {
        if (!$this->bot_token) return '-';
        return substr($this->bot_token, 0, 10) . '****' . substr($this->bot_token, -4);
    }

    /**
     * Get masked API key for display
     */
    public function getMaskedApiKeyAttribute(): string
    {
        if (!$this->pg_api_key) return '-';
        return substr($this->pg_api_key, 0, 6) . '****' . substr($this->pg_api_key, -4);
    }
}

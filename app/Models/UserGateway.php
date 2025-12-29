<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gateway_id',
        'credentials',
        'label',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array', // Use encrypted:array to auto-decrypt
        'is_active' => 'boolean',
    ];

    /**
     * The user who owns this gateway config
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The gateway type
     */
    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    /**
     * Bots using this gateway
     */
    public function bots()
    {
        return $this->hasMany(Bot::class, 'active_gateway_id');
    }

    /**
     * Get a specific credential value
     */
    public function getCredential(string $key, $default = null)
    {
        $creds = $this->credentials;
        if (is_array($creds)) {
            return $creds[$key] ?? $default;
        }
        return $default;
    }
}

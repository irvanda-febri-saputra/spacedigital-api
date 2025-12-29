<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'logo',
        'fee_percent',
        'fee_flat',
        'description',
        'required_fields',
        'is_active',
    ];

    protected $casts = [
        'required_fields' => 'array',
        'fee_percent' => 'decimal:2',
        'fee_flat' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all user configurations for this gateway
     */
    public function userGateways()
    {
        return $this->hasMany(UserGateway::class, 'gateway_id');
    }

    /**
     * Scope to only active gateways
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

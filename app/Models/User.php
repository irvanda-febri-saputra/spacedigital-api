<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'api_key',
        'api_token',
        'avatar_seed',
        'avatar_style',
        'session_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Generate a new API key for the user
     */
    public function generateApiKey(): string
    {
        $this->api_key = 'sd_live_' . bin2hex(random_bytes(24));
        $this->save();
        return $this->api_key;
    }

    /**
     * Regenerate API key (alias for generateApiKey)
     */
    public function regenerateApiKey(): string
    {
        return $this->generateApiKey();
    }

    /**
     * Get user's bots
     */
    public function bots()
    {
        return $this->hasMany(Bot::class);
    }

    /**
     * Get user's configured gateways
     */
    public function userGateways()
    {
        return $this->hasMany(UserGateway::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * The user this code belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new OTP code for a user
     */
    public static function generateFor(User $user): self
    {
        // Delete any existing codes for this user
        static::where('user_id', $user->id)->delete();

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return static::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10), // 10 minutes expiry
        ]);
    }

    /**
     * Verify OTP code for a user
     */
    public static function verify(User $user, string $code): bool
    {
        $record = static::where('user_id', $user->id)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if ($record) {
            // Delete the used code
            $record->delete();
            return true;
        }

        return false;
    }
}

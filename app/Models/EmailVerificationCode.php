<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a new OTP code for user
     */
    public static function generateFor(User $user): self
    {
        // Delete any existing codes for this user
        static::where('user_id', $user->id)->delete();

        // Generate 6 digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return static::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * Verify OTP code
     */
    public static function verify(User $user, string $code): bool
    {
        $record = static::where('user_id', $user->id)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if ($record) {
            // Mark email as verified
            $user->email_verified_at = now();
            $user->save();

            // Delete the code
            $record->delete();

            return true;
        }

        return false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

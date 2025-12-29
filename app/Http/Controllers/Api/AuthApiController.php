<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Models\PasswordResetCode;
use App\Notifications\SendVerificationOtp;
use App\Notifications\SendPasswordResetOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * Login and return user data with API token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'turnstile_token' => 'required|string',
        ]);

        // Validate Turnstile token
        $turnstileSecret = config('turnstile.secret_key');
        if ($turnstileSecret) {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $turnstileSecret,
                'response' => $request->turnstile_token,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification failed. Please try again.',
                ], 422);
            }
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email not registered',
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 401);
        }

        // Generate a simple token (in production, use Laravel Sanctum)
        $token = bin2hex(random_bytes(32));
        $user->update(['api_token' => hash('sha256', $token)]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_seed' => $user->avatar_seed,
                    'avatar_style' => $user->avatar_style,
                    'api_token' => $user->api_key,
                    'created_at' => $user->created_at,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Logout - invalidate token
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->update(['api_token' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_seed' => $user->avatar_seed,
                'avatar_style' => $user->avatar_style,
                'api_token' => $user->api_key,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Create notification for password change
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'password',
            'title' => 'Password Changed',
            'message' => 'Your account password has been successfully changed.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Update avatar
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar_seed' => 'required|string|max:255',
            'avatar_style' => 'required|string|max:50',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Avatar updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_seed' => $user->avatar_seed,
                'avatar_style' => $user->avatar_style,
                'api_token' => $user->api_key,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Regenerate API Key
     */
    public function regenerateApiKey(Request $request)
    {
        $user = $request->user();

        // Generate new API key
        $newApiKey = 'sd_live_' . bin2hex(random_bytes(32));
        $user->update(['api_key' => $newApiKey]);

        return response()->json([
            'success' => true,
            'message' => 'API Key regenerated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_seed' => $user->avatar_seed,
                'avatar_style' => $user->avatar_style,
                'api_token' => $user->api_key,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Register new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
            ],
            'turnstile_token' => 'required|string',
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain both uppercase and lowercase letters.',
        ]);

        // Validate Turnstile token
        $turnstileSecret = config('turnstile.secret_key');
        if ($turnstileSecret) {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $turnstileSecret,
                'response' => $request->turnstile_token,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification failed. Please try again.',
                ], 422);
            }
        }

        // Generate API key
        $apiKey = 'sd_live_' . bin2hex(random_bytes(16));

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active',
            'api_key' => $apiKey,
        ]);

        // Generate and send OTP
        $otpRecord = EmailVerificationCode::generateFor($user);
        $user->notify(new SendVerificationOtp($otpRecord->code));

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email for verification code.',
        ]);
    }

    /**
     * Verify email with OTP
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $verified = EmailVerificationCode::verify($user, $request->code);

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    /**
     * Resend email verification OTP
     */
    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.',
            ], 422);
        }

        // Generate and send new OTP
        $otpRecord = EmailVerificationCode::generateFor($user);
        $user->notify(new SendVerificationOtp($otpRecord->code));

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent successfully.',
        ]);
    }

    /**
     * Step 1: Send password reset OTP
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'turnstile_token' => 'required|string',
        ], [
            'email.exists' => 'No account found with this email address.',
        ]);

        // Validate Turnstile token
        $turnstileSecret = config('turnstile.secret_key');
        if ($turnstileSecret) {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $turnstileSecret,
                'response' => $request->turnstile_token,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification failed. Please try again.',
                ], 422);
            }
        }

        $user = User::where('email', $request->email)->first();

        // Generate and send OTP
        $otpRecord = PasswordResetCode::generateFor($user);
        $user->notify(new SendPasswordResetOtp($otpRecord->code));

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to your email.',
        ]);
    }

    /**
     * Step 2: Verify password reset OTP
     */
    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $otpRecord = PasswordResetCode::where('user_id', $user->id)
            ->where('code', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Resend password reset OTP
     */
    public function resendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Generate and send new OTP
        $otpRecord = PasswordResetCode::generateFor($user);
        $user->notify(new SendPasswordResetOtp($otpRecord->code));

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent successfully.',
        ]);
    }

    /**
     * Step 3: Set new password
     */
    public function setNewPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'confirmed',
            ],
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain uppercase and lowercase letters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Delete used OTP
        PasswordResetCode::where('user_id', $user->id)->delete();

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }
}

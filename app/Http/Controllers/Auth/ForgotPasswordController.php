<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordResetCode;
use App\Notifications\SendPasswordResetOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    /**
     * Step 1: Show the forgot password form (enter email)
     */
    public function show()
    {
        return Inertia::render('Auth/ForgotPassword', [
            'turnstileSiteKey' => config('turnstile.site_key'),
        ]);
    }

    /**
     * Step 1: Send OTP to email
     */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No account found with this email address.',
        ]);

        // Validate Turnstile if configured
        if (config('turnstile.secret_key')) {
            $request->validate([
                'cf-turnstile-response' => 'required',
            ], [
                'cf-turnstile-response.required' => 'Please complete the security verification.',
            ]);

            $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('turnstile.secret_key'),
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                return back()->withErrors([
                    'turnstile' => 'Security verification failed. Please try again.',
                ])->withInput();
            }
        }

        $user = User::where('email', $validated['email'])->first();

        // Generate and send OTP
        $otpRecord = PasswordResetCode::generateFor($user);
        $user->notify(new SendPasswordResetOtp($otpRecord->code));

        // Store email in session
        $request->session()->put('password_reset_email', $user->email);

        return redirect()->route('password.verify.form');
    }

    /**
     * Step 2: Show OTP verification form
     */
    public function showVerifyForm(Request $request)
    {
        $email = $request->session()->get('password_reset_email');

        if (!$email) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Please enter your email first.',
            ]);
        }

        return Inertia::render('Auth/VerifyResetOtp', [
            'email' => $email,
        ]);
    }

    /**
     * Step 2: Verify OTP code
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => 'required|string|size:6',
        ], [
            'otp.required' => 'Please enter the OTP code.',
            'otp.size' => 'OTP must be 6 digits.',
        ]);

        $email = $request->session()->get('password_reset_email');

        if (!$email) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return back()->withErrors(['otp' => 'User not found.']);
        }

        // Check OTP without deleting it (will be deleted after password reset)
        $otpRecord = PasswordResetCode::where('user_id', $user->id)
            ->where('code', $validated['otp'])
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return back()->withErrors([
                'otp' => 'Invalid or expired OTP code. Please try again.',
            ]);
        }

        // Mark OTP as verified in session
        $request->session()->put('password_reset_verified', true);

        return redirect()->route('password.new.form');
    }

    /**
     * Step 3: Show new password form
     */
    public function showNewPasswordForm(Request $request)
    {
        $email = $request->session()->get('password_reset_email');
        $verified = $request->session()->get('password_reset_verified');

        if (!$email || !$verified) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/NewPassword', [
            'email' => $email,
        ]);
    }

    /**
     * Step 3: Set new password
     */
    public function setNewPassword(Request $request)
    {
        $validated = $request->validate([
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

        $email = $request->session()->get('password_reset_email');
        $verified = $request->session()->get('password_reset_verified');

        if (!$email || !$verified) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('password.request');
        }

        // Delete used OTP
        PasswordResetCode::where('user_id', $user->id)->delete();

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Clear session
        $request->session()->forget(['password_reset_email', 'password_reset_verified']);

        return redirect()->route('login')->with('success', 'Password updated successfully! You can now login with your new password.');
    }

    /**
     * Resend OTP for password reset
     */
    public function resendOtp(Request $request)
    {
        $email = $request->session()->get('password_reset_email');

        if (!$email) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return back()->withErrors(['otp' => 'User not found.']);
        }

        // Generate and send new OTP
        $otpRecord = PasswordResetCode::generateFor($user);
        $user->notify(new SendPasswordResetOtp($otpRecord->code));

        return back()->with('success', 'New OTP sent to your email.');
    }
}

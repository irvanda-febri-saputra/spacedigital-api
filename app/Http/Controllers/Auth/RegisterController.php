<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\SendVerificationOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterController extends Controller
{
    /**
     * Show registration page
     */
    public function show()
    {
        return Inertia::render('Auth/Register', [
            'turnstileSiteKey' => config('turnstile.site_key'),
        ]);
    }

    /**
     * Handle registration
     * Throttled: 3 attempts per minute
     */
    public function store(Request $request)
    {
        // Validate form fields first to show proper errors
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => [
                'required', 
                'confirmed', 
                'min:8',
                'regex:/[a-z]/', // at least one lowercase
                'regex:/[A-Z]/', // at least one uppercase
            ],
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain both uppercase and lowercase letters.',
        ]);

        // Validate Turnstile token after form validation passes
        if (config('turnstile.secret_key')) {
            $turnstileToken = $request->input('turnstile_token');
            
            if (!$turnstileToken) {
                return back()->withErrors([
                    'turnstile' => 'Please complete the security verification.',
                ])->withInput();
            }

            $response = Http::asForm()->post(config('turnstile.verify_url'), [
                'secret' => config('turnstile.secret_key'),
                'response' => $turnstileToken,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                return back()->withErrors([
                    'turnstile' => 'Security verification expired. Please verify again.',
                ])->withInput();
            }
        }

        // Generate API key for the new user
        $apiKey = 'sd_live_' . bin2hex(random_bytes(16));

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active', // Active immediately after email verification
            'api_key' => $apiKey,
        ]);

        // Generate and send OTP
        $otpRecord = EmailVerificationCode::generateFor($user);
        $user->notify(new SendVerificationOtp($otpRecord->code));

        // Store email in session for verification
        $request->session()->put('verification_email', $user->email);

        // Redirect to verification page
        return redirect('/email/verify');
    }
}

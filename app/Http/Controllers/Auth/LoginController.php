<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class LoginController extends Controller
{
    /**
     * Show login page
     */
    public function show()
    {
        return Inertia::render('Auth/Login', [
            'turnstileSiteKey' => config('turnstile.site_key'),
        ]);
    }

    /**
     * Handle login attempt
     * Throttled: 5 attempts per minute
     */
    public function store(Request $request)
    {
        // Validate Turnstile token first
        if (config('turnstile.secret_key')) {
            $turnstileToken = $request->input('turnstile_token');
            
            if (!$turnstileToken) {
                return back()->withErrors([
                    'turnstile' => 'Please complete the security verification.',
                ]);
            }

            $response = Http::asForm()->post(config('turnstile.verify_url'), [
                'secret' => config('turnstile.secret_key'),
                'response' => $turnstileToken,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->json('success')) {
                \Illuminate\Support\Facades\Log::error('Turnstile validation failed', [
                    'response' => $response->json(),
                    'token' => substr($turnstileToken, 0, 10) . '...',
                    'ip' => $request->ip()
                ]);
                
                return back()->withErrors([
                    'turnstile' => 'Security verification failed. Please try again.',
                ]);
            }
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if email is registered first
        $user = \App\Models\User::where('email', $credentials['email'])->first();
        
        if (!$user) {
            return back()->withErrors([
                'email' => 'This email is not registered. Please create an account first.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            
            // Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                // Send new OTP and redirect to verification page
                $otpRecord = \App\Models\EmailVerificationCode::generateFor($user);
                $user->notify(new \App\Notifications\SendVerificationOtp($otpRecord->code));
                
                Auth::logout();
                $request->session()->put('verification_email', $user->email);
                
                return redirect('/email/verify')->with('message', 'Please verify your email. We sent a new code to your inbox.');
            }
            
            $request->session()->regenerate();

            // Generate unique session token for single session enforcement
            $sessionToken = bin2hex(random_bytes(32));
            $user->update(['session_token' => $sessionToken]);
            $request->session()->put('session_token', $sessionToken);

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Incorrect password. Please try again.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout
     */
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}

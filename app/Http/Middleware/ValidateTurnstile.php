<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ValidateTurnstile
{
    /**
     * Handle an incoming request.
     * Validates Cloudflare Turnstile token
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation if no secret key configured (development mode)
        $secretKey = config('turnstile.secret_key');
        if (empty($secretKey)) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response') ?? $request->input('turnstile_token');

        if (!$token) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification required. Please complete the CAPTCHA.',
                ], 422);
            }
            return back()->withErrors(['turnstile' => 'Please complete the security verification.']);
        }

        // Verify token with Cloudflare
        $response = Http::asForm()->post(config('turnstile.verify_url'), [
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        $result = $response->json();

        if (!$result['success']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification failed. Please try again.',
                ], 422);
            }
            return back()->withErrors(['turnstile' => 'Security verification failed. Please try again.']);
        }

        return $next($request);
    }
}

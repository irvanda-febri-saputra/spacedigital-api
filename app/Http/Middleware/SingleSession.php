<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SingleSession
{
    /**
     * Handle an incoming request.
     * 
     * Ensures user can only be logged in from one device/browser at a time.
     * If session_token doesn't match database, user is logged out.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                $sessionToken = $request->session()->get('session_token');
                $dbToken = $user->session_token;
                
                // Case 1: User has no token in database (newly migrated user)
                // Generate and save token for them
                if (empty($dbToken)) {
                    $newToken = bin2hex(random_bytes(32));
                    $user->update(['session_token' => $newToken]);
                    $request->session()->put('session_token', $newToken);
                    return $next($request);
                }
                
                // Case 2: User has no token in session (first request after migration)
                // Set the session token from database
                if (empty($sessionToken)) {
                    $request->session()->put('session_token', $dbToken);
                    return $next($request);
                }
                
                // Case 3: Tokens don't match - another device logged in
                if ($dbToken !== $sessionToken) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    
                    // For Inertia requests, return special response
                    if ($request->header('X-Inertia')) {
                        return response()->json([
                            'component' => 'Auth/Login',
                            'props' => [
                                'errors' => [],
                                'flash' => [
                                    'error' => 'You have been logged out because your account was accessed from another device.',
                                ],
                            ],
                            'url' => '/login',
                        ], 409, ['X-Inertia-Location' => '/login?session_expired=1']);
                    }
                    
                    return redirect('/login')->with('error', 'You have been logged out because your account was accessed from another device.');
                }
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Session data is corrupted, clear and redirect to login
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect('/login')->with('error', 'Your session has expired. Please login again.');
        }

        return $next($request);
    }
}

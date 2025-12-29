<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     * Validates X-API-Key header and attaches user to request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'API Key is required',
                'message' => 'Please provide X-API-Key header'
            ], 401);
        }
        
        $user = User::where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API Key',
                'message' => 'The provided API Key is invalid or user is not active'
            ], 401);
        }
        
        // Attach user to request for use in controllers
        $request->merge(['api_user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        return $next($request);
    }
}

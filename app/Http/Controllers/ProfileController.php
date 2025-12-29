<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        return Inertia::render('Profile/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_seed' => $user->avatar_seed,
                'avatar_style' => $user->avatar_style,
                'api_key' => $user->api_key ?? null,
                'created_at' => $user->created_at?->format('M d, Y'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'avatar_seed' => ['nullable', 'string', 'max:100'],
            'avatar_style' => ['nullable', 'string', 'max:50'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated successfully!');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully!');
    }

    public function updateWebhook(Request $request)
    {
        $validated = $request->validate([
            'callback_url' => ['nullable', 'url', 'max:500'],
        ]);

        $user = $request->user();
        $settings = $user->settings ?? [];
        $settings['callback_url'] = $validated['callback_url'];
        
        $user->update(['settings' => $settings]);

        return back()->with('success', 'Webhook URL updated successfully!');
    }

    /**
     * Show settings page (API Key, Change Password, Account Info)
     */
    public function settings()
    {
        $user = auth()->user();
        
        return Inertia::render('Settings/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'api_key' => $user->api_key ?? null,
                'created_at' => $user->created_at?->format('M d, Y'),
            ],
        ]);
    }

    /**
     * Regenerate API key for user
     */
    public function regenerateApiKey(Request $request)
    {
        $user = $request->user();
        
        // Generate new API key: sd_live_<random_32_chars>
        $newApiKey = 'sd_live_' . bin2hex(random_bytes(16));
        
        $user->update(['api_key' => $newApiKey]);

        return back()->with('success', 'API Key regenerated successfully!');
    }
}


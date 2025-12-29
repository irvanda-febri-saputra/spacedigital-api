<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private channel for user notifications
 * User subscribes to: private-user.{userId}
 */
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Private channel for payment updates
 * Customer bots subscribe to: private-payment.{api_key}
 * Authorization: API Key must be valid and belong to active user
 */
Broadcast::channel('payment.{apiKey}', function ($user, $apiKey) {
    // For API-authenticated requests, check if API key is valid
    if ($apiKey) {
        $keyUser = User::where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();
        return $keyUser !== null;
    }
    return false;
});

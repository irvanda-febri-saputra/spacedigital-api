<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Bot;

class BroadcastController extends Controller
{
    /**
     * Send broadcast message to bot users
     */
    public function send(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'bot_id' => 'required|exists:bots,id',
            'message' => 'required|string|max:4096',
            'image_url' => 'nullable|url|max:500',
            'format' => 'nullable|in:HTML,Markdown',
        ]);

        // Check bot ownership (semua user hanya bisa broadcast ke bot miliknya)
        $bot = Bot::findOrFail($validated['bot_id']);
        if ($bot->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $broadcastId = 'bc_' . time() . '_' . substr(uniqid(), -6);

        // Broadcast to bot via WebSocket
        try {
            $wsUrl = config('app.ws_hub_url', 'http://localhost:8080');
            $wsSecret = config('app.ws_broadcast_secret');

            $response = Http::timeout(10)->post("{$wsUrl}/broadcast", [
                'secret' => $wsSecret,
                'channel' => "bot.{$bot->id}",
                'event' => 'broadcast.send',
                'data' => [
                    'broadcast_id' => $broadcastId,
                    'message' => $validated['message'],
                    'image_url' => $validated['image_url'] ?? null,
                    'format' => $validated['format'] ?? 'HTML',
                    'timestamp' => now()->toIso8601String()
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info("Broadcast sent to bot {$bot->id}: {$broadcastId}");

                return response()->json([
                    'success' => true,
                    'broadcast_id' => $broadcastId,
                    'message' => 'Broadcast sent to bot successfully',
                    'clients' => $result['clients'] ?? 0
                ]);
            } else {
                Log::error("WebSocket broadcast failed: " . $response->body());
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send broadcast to bot'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Broadcast error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}

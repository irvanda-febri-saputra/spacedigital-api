<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket Hub Service
 * Service untuk mengirim broadcast ke Node.js WebSocket Hub
 */
class WebSocketHubService
{
    protected string $hubUrl;
    protected string $secret;
    protected int $timeout;

    public function __construct()
    {
        $this->hubUrl = rtrim(config('services.ws_hub.url', env('WS_HUB_URL', 'http://localhost:5068')), '/');
        $this->secret = config('services.ws_hub.secret', env('WS_BROADCAST_SECRET', ''));
        $this->timeout = config('services.ws_hub.timeout', 5);
    }

    /**
     * Broadcast event ke channel tertentu
     *
     * @param string $channel Channel tujuan (e.g., 'bot.1', 'user.123')
     * @param string $event Event name (e.g., 'payment.status.updated')
     * @param array $data Data yang akan di-broadcast
     * @return bool Success status
     */
    public function broadcast(string $channel, string $event, array $data = []): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Broadcast-Secret' => $this->secret,
                ])
                ->post("{$this->hubUrl}/broadcast", [
                    'channel' => $channel,
                    'event' => $event,
                    'data' => $data,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info("WebSocket broadcast success", [
                    'channel' => $channel,
                    'event' => $event,
                    'clients' => $result['clients'] ?? 0,
                ]);
                return true;
            }

            Log::error("WebSocket broadcast failed", [
                'channel' => $channel,
                'event' => $event,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("WebSocket broadcast error", [
                'channel' => $channel,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Broadcast payment status update ke bot
     *
     * @param int $botId Bot ID
     * @param string $orderId Order ID
     * @param string $status Status (success, pending, failed)
     * @param int|null $amount Amount
     * @param string|null $paidAt Paid at timestamp
     * @param string|null $gateway Payment gateway name
     * @return bool
     */
    public function broadcastPaymentStatus(
        int $botId,
        string $orderId,
        string $status,
        ?int $amount = null,
        ?string $paidAt = null,
        ?string $gateway = null
    ): bool {
        return $this->broadcast("bot.{$botId}", 'payment.status.updated', [
            'bot_id' => $botId,
            'order_id' => $orderId,
            'status' => $status,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'gateway' => $gateway,
        ]);
    }

    /**
     * Broadcast notification ke user dashboard
     *
     * @param int $userId User ID
     * @param array $notification Notification data
     * @return bool
     */
    public function broadcastNotification(int $userId, array $notification): bool
    {
        return $this->broadcast("user.{$userId}", 'notification.created', [
            'notification' => $notification,
        ]);
    }

    /**
     * Batch broadcast ke multiple channels
     *
     * @param array $broadcasts Array of ['channel' => ..., 'event' => ..., 'data' => ...]
     * @return bool
     */
    public function batchBroadcast(array $broadcasts): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Broadcast-Secret' => $this->secret,
                ])
                ->post("{$this->hubUrl}/broadcast/batch", [
                    'broadcasts' => $broadcasts,
                ]);

            if ($response->successful()) {
                Log::info("WebSocket batch broadcast success", [
                    'count' => count($broadcasts),
                ]);
                return true;
            }

            Log::error("WebSocket batch broadcast failed", [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("WebSocket batch broadcast error", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check WS Hub health status
     *
     * @return array|null
     */
    public function getStatus(): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->hubUrl}/status");

            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Exception $e) {
            Log::error("WebSocket Hub status check failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

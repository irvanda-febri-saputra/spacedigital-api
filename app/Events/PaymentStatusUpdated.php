<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $botId;
    public string $orderId;
    public string $status;
    public ?int $amount;
    public ?string $paidAt;

    /**
     * Create a new event instance.
     */
    public function __construct(int $botId, string $orderId, string $status, ?int $amount = null, ?string $paidAt = null)
    {
        $this->botId = $botId;
        $this->orderId = $orderId;
        $this->status = $status;
        $this->amount = $amount;
        $this->paidAt = $paidAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Public channel for bot - no authentication needed
        return [
            new Channel('bot.' . $this->botId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.status.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'bot_id' => $this->botId,
            'order_id' => $this->orderId,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paidAt,
        ];
    }
}

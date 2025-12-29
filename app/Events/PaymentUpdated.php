<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentUpdated Event
 * 
 * Broadcasts payment status updates to customer bots via WebSocket.
 * Customer bots listen on private-payment.{api_key} channel.
 */
class PaymentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $orderId;
    public $status;
    public $amount;
    public $paidAt;
    public $apiKey;

    /**
     * Create a new event instance.
     */
    public function __construct(Transaction $transaction, string $apiKey)
    {
        $this->orderId = $transaction->order_id;
        $this->status = $transaction->status;
        $this->amount = $transaction->total_price;
        $this->paidAt = $transaction->paid_at;
        $this->apiKey = $apiKey;
    }

    /**
     * Get the channels the event should broadcast on.
     * 
     * Customer bots subscribe to: private-payment.{api_key}
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('payment.' . $this->apiKey),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'transaction_id' => $this->orderId,
            'order_id' => $this->orderId,
            'status' => $this->status,
            'amount' => $this->amount,
            'paid_at' => $this->paidAt,
        ];
    }
}

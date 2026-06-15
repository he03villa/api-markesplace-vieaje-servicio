<?php

namespace App\Events;

use App\Models\ServiceRequestDelivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ServiceRequestDelivery $delivery,
        public string $status,
        public int $notifyUserId
    )
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->notifyUserId}"),
            new PrivateChannel("service.{$this->delivery->service_request_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id'        => $this->delivery->id,
            'service_request_id' => $this->delivery->service_request_id,
            'status'             => $this->status,
            'worker_id'          => $this->delivery->worker_id,
        ];
    }

    public function broadcastAs(): string
    {
        return 'DeliveryStatusChanged';
    }
}

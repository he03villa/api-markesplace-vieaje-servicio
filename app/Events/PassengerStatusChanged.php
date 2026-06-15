<?php

namespace App\Events;

use App\Models\RideRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PassengerStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public RideRequest $ride,
        public int $passengerId,
        public string $status,
        public ?string $reason = null
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
            // Al pasajero afectado directamente
            new PrivateChannel("user.{$this->passengerId}"),
            // Al canal del viaje (por si el conductor también escucha)
            new PrivateChannel("ride.{$this->ride->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ride_id'    => $this->ride->id,
            'passenger_id' => $this->passengerId,
            'status'     => $this->status,
            'reason'     => $this->reason,
        ];
    }

    public function broadcastAs(): string
    {
        return 'PassengerStatusChanged';
    }
}

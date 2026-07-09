<?php

namespace App\Events;

use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PassengerJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public RideRequest $ride,
        public User $passenger,
        public int $seats = 1
    ) {
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
            new PrivateChannel("user.{$this->ride->driver_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PassengerJoined';
    }

    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'passenger' => [
                'id' => $this->passenger->id,
                'name' => $this->passenger->name,
                'avatar_url' => $this->passenger->avatar_url,
            ],
            'seats' => $this->seats,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

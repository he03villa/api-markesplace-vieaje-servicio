<?php

namespace App\Events;

use App\Models\Offer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Offer $offer;

    /**
     * Create a new event instance.
     */
    public function __construct(Offer $offer)
    {
        $this->offer = $offer->load(['user', 'serviceRequest']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('service.' . $this->offer->service_request_id),
            new PrivateChannel('user.' . $this->offer->serviceRequest->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'offer.created';
    }

    public function broadcastWith(): array
    {
        return [
            'offer' => [
                'id' => $this->offer->id,
                'price' => $this->offer->price,
                'description' => $this->offer->description,
                'estimated_time' => $this->offer->estimated_time,
                'status' => $this->offer->status,
                'created_at' => $this->offer->created_at,
                'user' => [
                    'id' => $this->offer->user->id,
                    'name' => $this->offer->user->name,
                    'avatar_url' => $this->offer->user->avatar_url,
                    'rating' => $this->offer->user->rating,
                ],
            ],
            'service_request_id' => $this->offer->service_request_id,
            'service_title' => $this->offer->serviceRequest->title,
            'message' => 'Nueva oferta recibida',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

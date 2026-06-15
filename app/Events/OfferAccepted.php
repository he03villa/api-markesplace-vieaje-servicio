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

class OfferAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Offer $offer;
    public array $rejectedOffers;

    /**
     * Create a new event instance.
     */
    public function __construct(Offer $offer, array $rejectedOffers = [])
    {
        $this->offer = $offer->load(['user', 'serviceRequest']);
        $this->rejectedOffers = $rejectedOffers;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal del servicio (para todos los que están viendo el servicio)
            new PrivateChannel('service.' . $this->offer->service_request_id),
            // Canal del usuario que hizo la oferta aceptada
            new PrivateChannel('user.' . $this->offer->user_id),
            // Canal del dueño del servicio
            new PrivateChannel('user.' . $this->offer->serviceRequest->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'offer.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'offer' => [
                'id' => $this->offer->id,
                'price' => $this->offer->price,
                'status' => $this->offer->status,
                'user' => [
                    'id' => $this->offer->user->id,
                    'name' => $this->offer->user->name,
                    'avatar_url' => $this->offer->user->avatar_url,
                ],
            ],
            'service_request' => [
                'id' => $this->offer->serviceRequest->id,
                'title' => $this->offer->serviceRequest->title,
                'status' => 'in_progress',
            ],
            'rejected_offers' => $this->rejectedOffers, // IDs de ofertas rechazadas
            'message' => 'Oferta aceptada',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

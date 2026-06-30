<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationExploreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = $this->ui_metadata ?? [];

        return [
            'id'           => $this->id,
            'type'         => $this->category,         // service | ride
            'type_label'   => $this->type_label,
            'title'        => $this->title,
            'description'  => $this->description,
            'category'     => $this->category,
            'sub_category' => $this->sub_category,
            'status'       => $this->status,
            'status_label' => $this->status_label,
            'badge'        => $this->badge,
            'offers_count' => $this->offers_count,
            'views_count'  => $this->views_count,
            'published_at' => $this->published_at?->toISOString(),

            // Datos del publicador
            'user' => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar_url ?? null,
                'rating' => $this->user->rating ?? 0,
            ],

            // Metadata de UI (badge, location, precio, etc.)
            'meta' => $meta,

            // Datos específicos según tipo
            'detail' => $this->whenLoaded('publishable', function () {
                return match ($this->category) {
                    'service' => $this->serviceDetail(),
                    'ride'    => $this->rideDetail(),
                    default   => null,
                };
            }),
        ];
    }

    private function serviceDetail(): array
    {
        $s = $this->publishable;
        return [
            'budget_min'   => $s->budget_min,
            'budget_max'   => $s->budget_max,
            'budget_range' => $this->ui_metadata['budget_range'] ?? null,
            'deadline'     => $s->deadline?->toISOString(),
            'address'      => $s->address,
            'location'     => $this->ui_metadata['location'] ?? null,
            'images'       => $s->image_urls ?? [],
            'latitude'     => $s->latitude,
            'longitude'    => $s->longitude,
        ];
    }

    private function rideDetail(): array
    {
        $r = $this->publishable;
        return [
            'origin'           => $r->origin_address ?? null,
            'destination'      => $r->destination_address ?? null,
            'departure_time'   => $r->departure_time?->toISOString(),
            'price_per_seat'   => $r->price_per_seat,
            'seats_available'  => $r->available_seats,
            'seats_total'      => $r->total_seats,
            'distance_km'      => $r->estimated_distance,
            'vehicle'          => "{$r->vehicle_color} {$r->vehicle_make} {$r->vehicle_model}",
            'origin_lat'       => $r->origin_lat,
            'origin_lng'       => $r->origin_lng,
        ];
    }
}

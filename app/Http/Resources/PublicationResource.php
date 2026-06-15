<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'published_at' => $this->published_at,
            'published_at_human' => $this->published_at?->locale('es')?->diffForHumans(),
            'offers_count' => $this->offers_count,
            'views_count' => $this->views_count,
            'badge' => $this->badge,
            'ui_metadata' => $this->ui_metadata,

            // Relación polimórfica con resource específico
            'details' => $this->whenLoaded('publishable', function () {
                return $this->getDetailsResource();
            }),
        ];
    }

    /**
     * Obtiene el resource específico según el tipo de publicación
     */
    protected function getDetailsResource(): ?array
    {
        if (!$this->publishable) {
            return null;
        }

        $resource = match ($this->category) {
            'service' => new ServiceRequestDetailsResource($this->publishable),
            'ride' => new RideRequestDetailsResource($this->publishable),
            default => null,
        };

        // Convertir a array si es un Resource
        return $resource?->resolve();
    }
}

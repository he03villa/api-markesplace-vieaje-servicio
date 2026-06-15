<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestDetailsResource extends JsonResource
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
            'category' => $this->category,
            'location' => $this->full_location,
            'address' => $this->address,
            'coordinates' => [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
            ],
            'budget' => [
                'min' => $this->budget_min,
                'max' => $this->budget_max,
                'range' => $this->getBudgetRangeText(),
            ],
            'deadline' => $this->deadline,
            'deadline_human' => $this->deadline?->locale('es')?->diffForHumans(),
            'images' => $this->images ?? [],
            'country' => [
                'name' => $this->country?->name,
                'code' => $this->country_code,
                'flag' => $this->country_flag,
            ],
            'city' => $this->city?->name,
        ];
    }

    private function getBudgetRangeText(): ?string
    {
        if ($this->budget_min && $this->budget_max) {
            return "$" . number_format($this->budget_min, 0) . " - $" . number_format($this->budget_max, 0);
        }
        if ($this->budget_min) {
            return "Desde $" . number_format($this->budget_min, 0);
        }
        if ($this->budget_max) {
            return "Hasta $" . number_format($this->budget_max, 0);
        }
        return null;
    }
}

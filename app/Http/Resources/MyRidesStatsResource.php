<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyRidesStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'upcoming'         => $this->resource['upcoming']          ?? 0,
            'completed'        => $this->resource['completed']         ?? 0,
            'cancelled'        => $this->resource['cancelled']         ?? 0,
            'in_progress'      => $this->resource['in_progress']       ?? 0,
            'total'            => $this->resource['total']             ?? 0,
            'total_passengers' => $this->resource['total_passengers']  ?? 0,
            'total_earnings'   => $this->resource['total_earnings']    ?? 0,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyServicesStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total'          => $this->resource['total']          ?? 0,
            'active'         => $this->resource['active']         ?? 0,
            'completed'      => $this->resource['completed']      ?? 0,
            'paused'         => $this->resource['paused']         ?? 0,
            'pending'        => $this->resource['pending']        ?? 0,
            'total_views'    => $this->resource['total_views']    ?? 0,
            'total_earnings' => $this->resource['total_earnings'] ?? 0,
        ];
    }
}

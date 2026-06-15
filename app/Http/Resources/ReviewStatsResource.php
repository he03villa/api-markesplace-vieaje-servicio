<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviews = $this->reviews()->get();
        $total   = $reviews->count();

        $breakdown = collect([5, 4, 3, 2, 1])->map(fn($stars) => [
            'stars'      => $stars,
            'count'      => $reviews->where('rating', $stars)->count(),
            'percentage' => $total > 0 ? round(($reviews->where('rating', $stars)->count() / $total) * 100) : 0,
        ]);

        return [
            'profile_rating'   => round($reviews->avg('rating') ?? 0, 1),
            'total_reviews'    => $total,
            'rating_breakdown' => $breakdown,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'rating'        => $this->rating,
            'text'          => $this->comment,
            'relative_time' => $this->created_at->diffForHumans(),
            'date'          => $this->created_at->toDateString(),
            'likes'         => $this->likes_count ?? 0,
            'helpful'       => $this->helpful_votes_count ?? 0,
            'serviceType'   => $this->category,
            'liked' => $request->user()
            ? $this->likes->contains('id', $request->user()->id)
            : false,
            'reviewer' => [
                'id'           => $this->reviewer->id,
                'name'         => $this->reviewer->name,
                'avatar'       => $this->reviewer->avatar_url,
                'verified'     => $this->reviewer->email_verified_at !== null,
                'review_count' => $this->reviewer->givenReviews()->count(),
            ],

            'reply' => $this->whenLoaded(
                'reply',
                fn() =>
                $this->reply ? [
                    'id'     => $this->reply->id,
                    'author' => $this->reply->author->name,
                    'avatar' => $this->reply->author->avatar_url,
                    'text'   => $this->reply->text,
                    'date'   => $this->reply->created_at->toDateString(),
                ] : null
            ),
        ];
    }
}

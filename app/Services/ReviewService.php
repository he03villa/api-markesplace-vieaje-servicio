<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Exceptions\CannotReviewYourselfException;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService
{
    public function createReview(User $reviewer, array $data): Review
    {
        if ($data['reviewed_user_id'] == $reviewer->id) {
            throw new CannotReviewYourselfException();
        }

        $review = $reviewer->givenReviews()->create($data);

        $reviewedUser = User::findOrFail($data['reviewed_user_id']);
        $reviewedUser->updateRating();

        return $review;
    }

    public function getUserReviews(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Review::where('reviewed_user_id', $userId)
            ->with(['reviewer', 'reply.author', 'likes', 'helpfulVotes'])
            ->withCount(['likes', 'helpfulVotes']);

        // Filtro por estrellas
        if (isset($filters['filter']) && in_array($filters['filter'], ['1', '2', '3', '4', '5'])) {
            $query->where('rating', (int) $filters['filter']);
        }

        // Filtro con respuesta
        if (($filters['filter'] ?? null) === 'with-reply') {
            $query->has('reply');
        }

        // Ordenamiento
        match ($filters['sort'] ?? 'recent') {
            'helpful' => $query->orderByDesc('helpful_votes_count'),
            'highest' => $query->orderByDesc('rating'),
            'lowest'  => $query->orderByAsc('rating'),
            default   => $query->latest(),
        };

        return $query->paginate(10);
    }
}

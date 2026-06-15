<?php

namespace App\Http\Controllers;

use App\Exceptions\CannotReviewYourselfException;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\ReviewStatsResource;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ReviewService $reviewService
    ) {}

    public function store(StoreReviewRequest $request, User $user): JsonResponse
    {
        try {
            $review = $this->reviewService->createReview(
                reviewer: $request->user(),
                data: array_merge($request->validated(), [
                    'reviewed_user_id' => $user->id,
                ])
            );

            return $this->successResponse(
                new ReviewResource($review->load('reviewer')),
                'Reseña creada exitosamente',
                201
            );
        } catch (CannotReviewYourselfException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear la reseña', 500);
        }
    }

    public function userReviews(int $userId): JsonResponse
    {
        try {
            $reviews = $this->reviewService->getUserReviews($userId);
            return $this->successResponse($reviews, 'Reseñas obtenidas exitosamente');
        } catch (\Exception $e) {
            Log::error('Error en user-reviews: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener las reseñas', 500);
        }
    }

    public function index(Request $request, User $user): JsonResponse
    {
        try {
            $reviews = $this->reviewService->getUserReviews($user->id, $request->only(['filter', 'sort']));

            $dataResponse = [
                'data' => ReviewResource::collection($reviews),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page'    => $reviews->lastPage(),
                    'total'        => $reviews->total(),
                    'has_more'     => $reviews->hasMorePages(),
                ],
                'stats' => new ReviewStatsResource($user),
            ];
            return $this->successResponse($dataResponse, 'Reseñas obtenidas exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Usuario no encontrado');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Error en reviews: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener las reseñas', 500);
        }
    }

    public function like(Request $request, Review $review): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $alreadyLiked = $review->likes()->where('user_id', $userId)->exists();

            if ($alreadyLiked) {
                $review->likes()->detach($userId);
            } else {
                $review->likes()->attach($userId);
            }

            $dataResponse = [
                'liked'       => !$alreadyLiked,
                'likes_count' => $review->likes()->count(),
            ];

            return $this->successResponse($dataResponse, 'Reseña actualizada exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reseña no encontrada');
        } catch (\Exception $th) {
            return $this->errorResponse('Error al actualizar la reseña', 500);
        }
    }

    public function helpful(Request $request, Review $review): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $already = $review->helpfulVotes()->where('user_id', $userId)->exists();

            if (!$already) {
                $review->helpfulVotes()->attach($userId);
            }

            $dataResponse = [
                'helpful_count' => $review->helpfulVotes()->count(),
            ];

            return $this->successResponse($dataResponse, 'Reseña actualizada exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reseña no encontrada');
        } catch (\Exception $th) {
            return $this->errorResponse('Error al actualizar la reseña', 500);
        }
    }

    public function report(Request $request, Review $review): JsonResponse
    {
        try {
            $request->validate(['reason' => 'nullable|string|max:255']);

            $userId = $request->user()->id;

            $already = $review->reports()->where('user_id', $userId)->exists();

            if (!$already) {
                $review->reports()->attach($userId, [
                    'reason' => $request->reason ?? 'Sin motivo especificado',
                ]);
            }
            return $this->successResponse(null, 'Reseña reportada. La revisaremos.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reseña no encontrada');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $th) {
            Log::error('Error en report: ' . $th->getMessage());
            return $this->errorResponse('Error al actualizar la reseña', 500);
        }
    }
}

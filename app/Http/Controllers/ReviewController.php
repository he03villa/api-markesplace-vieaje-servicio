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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ReviewService $reviewService
    ) {}

    #[OA\Post(
        path: '/api/users/{user}/reviews',
        tags: ['Reseñas'],
        summary: 'Crear una reseña para un usuario',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, description: 'Calificación del 1 al 5'),
                    new OA\Property(property: 'comment', type: 'string', minLength: 10, maxLength: 1000, description: 'Comentario de la reseña'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reseña creada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'No puedes reseñarte a ti mismo',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al crear la reseña',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/api/reviews/users/{userId}',
        tags: ['Reseñas'],
        summary: 'Obtener reseñas de un usuario por ID',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'userId', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reseñas obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener las reseñas',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/api/users/{user}/reviews',
        tags: ['Reseñas'],
        summary: 'Obtener reseñas de un usuario con paginación y filtros',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'filter', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtro adicional'),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Ordenamiento'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reseñas obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Usuario no encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener las reseñas',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Post(
        path: '/api/reviews/{review}/like',
        tags: ['Reseñas'],
        summary: 'Dar o quitar like a una reseña',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'review', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reseña actualizada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Reseña no encontrada',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al actualizar la reseña',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Post(
        path: '/api/reviews/{review}/helpful',
        tags: ['Reseñas'],
        summary: 'Marcar una reseña como útil',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'review', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reseña actualizada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Reseña no encontrada',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al actualizar la reseña',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Post(
        path: '/api/reviews/{review}/report',
        tags: ['Reseñas'],
        summary: 'Reportar una reseña',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'review', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', maxLength: 255, description: 'Motivo del reporte'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reseña reportada. La revisaremos.',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Reseña no encontrada',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al actualizar la reseña',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

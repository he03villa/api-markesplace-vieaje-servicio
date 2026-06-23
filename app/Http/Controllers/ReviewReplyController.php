<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewReplyRequest;
use App\Http\Requests\UpdateReviewReplyRequest;
use App\Models\Review;
use App\Services\ReviewReplyService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReviewReplyController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ReviewReplyService $reviewReplyService
    ) {}

    #[OA\Post(
        path: '/api/reviews/{review}/reply',
        tags: ['Reseñas'],
        summary: 'Crear una respuesta a una reseña',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'review', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'text', type: 'string', minLength: 10, maxLength: 500, description: 'Texto de la respuesta'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Respuesta creada exitosamente',
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
    public function store(StoreReviewReplyRequest $request, Review $review): JsonResponse
    {
        try {
            $response = $this->reviewReplyService->store($request, $review);
            return $this->successResponse($response['data'], $response['message']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reseña no encontrada');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $th) {
            return $this->errorResponse('Error al actualizar la reseña', 500);
        }
    }

    #[OA\Put(
        path: '/api/reviews/{review}/reply',
        tags: ['Reseñas'],
        summary: 'Actualizar una respuesta a una reseña',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'review', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'text', type: 'string', minLength: 10, maxLength: 500, description: 'Texto de la respuesta'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Respuesta actualizada exitosamente',
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
    public function update(UpdateReviewReplyRequest $request, Review $review): JsonResponse
    {
        try {
            $response = $this->reviewReplyService->update($request, $review);
            return $this->successResponse($response['data'], $response['message']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reseña no encontrada');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $th) {
            return $this->errorResponse('Error al actualizar la reseña', 500);
        }
    }

    #[OA\Delete(
        path: '/api/reviews/{review}/reply',
        tags: ['Reseñas'],
        summary: 'Eliminar una respuesta a una reseña',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'review', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Respuesta eliminada exitosamente',
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
    public function destroy(Review $review): JsonResponse
    {
        try {
            $response = $this->reviewReplyService->destroy($review);
            return $this->successResponse(null, $response['message']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reseña no encontrada');
        } catch (\Exception $th) {
            return $this->errorResponse('Error al actualizar la reseña', 500);
        }
    }
}

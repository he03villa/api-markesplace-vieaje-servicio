<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewReplyRequest;
use App\Http\Requests\UpdateReviewReplyRequest;
use App\Models\Review;
use App\Services\ReviewReplyService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewReplyController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ReviewReplyService $reviewReplyService
    ) {}

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

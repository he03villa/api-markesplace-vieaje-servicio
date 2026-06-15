<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceRequestClosedException;
use App\Services\OfferService;
use App\Services\ServiceRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OfferController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private OfferService $offerService
    ) {}

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'service_request_id' => 'required|exists:service_requests,id',
                'price' => 'required|numeric|min:0',
                'description' => 'required|string',
                'estimated_time' => 'nullable|string',
            ]);

            $offer = $this->offerService->getOfferByUser($request->user(), $validated['service_request_id']);

            if ($offer) {
                return $this->errorResponse('Ya has creado una oferta para esta solicitud', 400);
            }

            $_serviceRequestService = new ServiceRequestService();
            $seserviceRequest = $_serviceRequestService->findRequest($validated['service_request_id']);

            if ($request->user()->id === $seserviceRequest->user_id) {
                return $this->errorResponse('No puedes ofertar tu propia solicitud', 400);
            }

            $offer = $this->offerService->createOffer($request->user(), $validated);

            return $this->successResponse(
                $offer->load('user'),
                'Oferta creada exitosamente',
                201
            );
        } catch (ServiceRequestClosedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error("Error al crear la oferta: " . $e->getMessage(), ['request' => $request->all(), 'exception' => $e, 'trace' => $e->getTraceAsString(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('Error al crear la oferta', 500);
        }
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        try {
            $offer = $this->offerService->findOffer($id, ['serviceRequest']);
            
            if (!$offer->serviceRequest->canBeEditedBy($request->user())) {
                return $this->forbiddenResponse('No tienes permiso para aceptar esta oferta');
            }

            $accepted = $this->offerService->acceptOffer($offer);

            return $this->successResponse($accepted, 'Oferta aceptada exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Oferta no encontrada');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al aceptar la oferta', 500);
        }
    }

    public function myOffers(Request $request): JsonResponse
    {
        try {
            $offers = $this->offerService->getUserOffers($request->user());
            return $this->successResponse($offers, 'Tus ofertas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tus ofertas', 500);
        }
    }
}

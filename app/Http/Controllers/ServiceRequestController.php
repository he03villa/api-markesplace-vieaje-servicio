<?php

namespace App\Http\Controllers;

use App\Http\Requests\MyServicesRequest;
use App\Services\PublicationService;
use App\Services\ServiceRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ServiceRequestService $serviceRequestService,
        private readonly PublicationService $publicationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['category', 'lat', 'lng', 'radius']);
            $requests = $this->serviceRequestService->getAvailableRequests($filters);

            return $this->successResponse($requests, 'Solicitudes obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las solicitudes', 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category' => 'required|string',
                'address' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'budget_min' => 'nullable|numeric|min:0',
                'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
                'deadline' => 'nullable|date|after:now',
                'images.*' => 'nullable|image|max:2048',
            ]);

            $serviceRequest = $this->serviceRequestService->createRequest(
                $request->user(),
                $validated
            );

            return $this->successResponse(
                $serviceRequest,
                'Solicitud creada exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear la solicitud', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $serviceRequest = $this->serviceRequestService->findRequest($id, ['user.about', 'offers.user', 'worker', 'serviceRequestsDelivered']);
            return $this->successResponse($serviceRequest);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Solicitud no encontrada');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la solicitud', 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $serviceRequest = $this->serviceRequestService->findRequest($id);

            if (!$serviceRequest->canBeEditedBy($request->user())) {
                return $this->forbiddenResponse('No tienes permiso para editar esta solicitud');
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'status' => 'sometimes|in:open,in_progress,completed,cancelled',
            ]);

            $updated = $this->serviceRequestService->updateRequest($serviceRequest, $validated);

            return $this->successResponse($updated, 'Solicitud actualizada exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Solicitud no encontrada');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar la solicitud', 500);
        }
    }

    public function myRequests(Request $request): JsonResponse
    {
        try {
            $requests = $this->serviceRequestService->getUserRequests($request->user());
            return $this->successResponse($requests, 'Tus solicitudes obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tus solicitudes', 500);
        }
    }

    public function myServiceRequests(MyServicesRequest $request): JsonResponse
    {
        try {
            $user   = $request->user();
            $result = $this->publicationService->getMyServices($user, $request);
            $dataResponse = [
                'services' => $result['publications'],        // colección paginada con resource
                'stats' => $result['stats'],              // header cards
                'meta' => [
                    'current_page' => $result['publications']->currentPage(),
                    'last_page'    => $result['publications']->lastPage(),
                    'per_page'     => $result['publications']->perPage(),
                    'total'        => $result['publications']->total(),
                ],
            ];
            return $this->successResponse($dataResponse, 'Tus solicitudes obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tus solicitudes', 500);
        }
    }
}

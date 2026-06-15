<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestDelivery;
use App\Services\DeliveryService;
use App\Services\ReviewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DeliveryService $deliveryService,
        private ReviewService $reviewService
    ) {}

    /**
     * Worker: Entregar trabajo con evidencia
     */
    public function submit(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $validated = $request->validate([
                'completion_notes' => 'required|string|min:10|max:3000',
                'actual_hours'     => 'nullable|numeric|min:0|max:999',
                'evidence_images'  => 'required|array|min:1|max:5',
                'evidence_images.*'=> 'image|mimes:jpeg,png,jpg,webp|max:5120',
                'evidence_docs'    => 'nullable|array|max:3',
                'evidence_docs.*'  => 'file|mimes:pdf,doc,docx|max:10240',
            ]);

            if ($request->hasFile('evidence_images')) {
                $validated['evidence_images'] = $request->file('evidence_images');
            }
            if ($request->hasFile('evidence_docs')) {
                $validated['evidence_docs'] = $request->file('evidence_docs');
            }

            $delivery = $this->deliveryService->submitDelivery(
                $serviceRequest, $request->user(), $validated
            );

            return $this->successResponse(
                $delivery->load(['worker', 'serviceRequest']),
                'Trabajo entregado exitosamente. Esperando aprobacion del cliente.',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    /**
     * Ver entrega de una solicitud
     */
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $delivery = $this->deliveryService->getDelivery($serviceRequest);
            if (!$delivery) return $this->notFoundResponse('No hay entrega para esta solicitud');

            $user = $request->user();
            $isOwner = $serviceRequest->user_id === $user->id;
            $isWorker = $delivery->worker_id === $user->id;
            if (!$isOwner && !$isWorker) return $this->forbiddenResponse('No autorizado');

            return $this->successResponse($delivery);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener entrega', 500);
        }
    }

    /**
     * Cliente: Aprobar, rechazar o solicitar revision
     */
    public function respond(Request $request, ServiceRequestDelivery $delivery): JsonResponse
    {
        try {
            $validated = $request->validate([
                'action'   => 'required|in:approve,reject,revision',
                'feedback' => 'required_if:action,reject,revision|string|max:2000',
                'rating'   => 'required_if:action,approve|integer|min:1|max:5',
                'comment'  => 'nullable|string|max:2000',
            ]);

            $client = $request->user();
            $action = $validated['action'];

            switch ($action) {
                case 'approve':
                    $delivery = $this->deliveryService->approveDelivery(
                        $delivery, $client, $validated['feedback'] ?? null
                    );
                    if (!empty($validated['rating'])) {
                        $this->reviewService->createReview($client, [
                            'reviewed_user_id' => $delivery->worker_id,
                            'reviewable_type'  => ServiceRequest::class,
                            'reviewable_id'    => $delivery->service_request_id,
                            'rating'           => $validated['rating'],
                            'comment'          => $validated['comment'] ?? null,
                        ]);
                    }
                    $message = 'Trabajo aprobado exitosamente';
                    break;

                case 'reject':
                    $delivery = $this->deliveryService->rejectDelivery(
                        $delivery, $client, $validated['feedback']
                    );
                    $message = 'Entrega rechazada';
                    break;

                case 'revision':
                    $delivery = $this->deliveryService->requestRevision(
                        $delivery, $client, $validated['feedback']
                    );
                    $message = 'Se solicitaron correcciones al worker';
                    break;
            }

            return $this->successResponse(
                $delivery->load(['worker', 'serviceRequest', 'approver']),
                $message
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    /**
     * Worker: Mis entregas
     */
    public function myDeliveries(Request $request): JsonResponse
    {
        try {
            $deliveries = $this->deliveryService->getWorkerDeliveries(
                $request->user(), $request->only('status')
            );
            return $this->successResponse($deliveries, 'Mis entregas obtenidas');
        } catch (\Exception $e) {
            return $this->errorResponse('Error', 500);
        }
    }

    /**
     * Cliente: Entregas pendientes de aprobacion
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        try {
            $deliveries = $this->deliveryService->getPendingApprovals($request->user());
            return $this->successResponse($deliveries, 'Entregas pendientes');
        } catch (\Exception $e) {
            return $this->errorResponse('Error', 500);
        }
    }
}

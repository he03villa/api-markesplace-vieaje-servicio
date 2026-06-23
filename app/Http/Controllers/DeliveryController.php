<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestDelivery;
use App\Services\DeliveryService;
use App\Services\ReviewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DeliveryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private DeliveryService $deliveryService,
        private ReviewService $reviewService
    ) {}

    #[OA\Post(
        path: '/api/service-requests/{serviceRequest}/deliver',
        tags: ['Entregas'],
        summary: 'Worker: Entregar trabajo con evidencia',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'serviceRequest', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['completion_notes', 'evidence_images'],
                    properties: [
                        new OA\Property(property: 'completion_notes', type: 'string'),
                        new OA\Property(property: 'actual_hours', type: 'number'),
                        new OA\Property(property: 'evidence_images', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                        new OA\Property(property: 'evidence_docs', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Trabajo entregado exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 403, description: 'No autorizado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/api/service-requests/{serviceRequest}/delivery',
        tags: ['Entregas'],
        summary: 'Ver entrega de una solicitud',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'serviceRequest', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Entrega obtenida',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 401, description: 'No autorizado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 403, description: 'No tienes permiso para ver esta entrega',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 404, description: 'No hay entrega para esta solicitud',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener entrega',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Post(
        path: '/api/deliveries/{delivery}/respond',
        tags: ['Entregas'],
        summary: 'Cliente: Aprobar, rechazar o solicitar revision',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'delivery', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(property: 'action', type: 'string', enum: ['approve', 'reject', 'revision']),
                    new OA\Property(property: 'feedback', type: 'string'),
                    new OA\Property(property: 'rating', type: 'integer'),
                    new OA\Property(property: 'comment', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Respuesta procesada exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 403, description: 'No autorizado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/api/my-deliveries',
        tags: ['Entregas'],
        summary: 'Worker: Mis entregas',
        security: [['jwt' => []]],
        parameters: [
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mis entregas obtenidas',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/api/pending-approvals',
        tags: ['Entregas'],
        summary: 'Cliente: Entregas pendientes de aprobacion',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Entregas pendientes',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
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

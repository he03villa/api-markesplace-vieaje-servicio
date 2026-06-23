<?php

namespace App\Http\Controllers;

use App\Exceptions\CannotJoinOwnRideException;
use App\Exceptions\InsufficientSeatsException;
use App\Http\Requests\DriverStatsRequest;
use App\Http\Requests\MyRidesRequest;
use App\Http\Resources\DriverStatsResource;
use App\Http\Resources\RideRequestCardResourceCollection;
use App\Http\Resources\RideRequestDetailsResource;
use App\Services\PublicationService;
use App\Services\RideRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class RideRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private RideRequestService $rideRequestService,
        private readonly PublicationService $publicationService
    ) {}

    #[OA\Get(
        path: '/api/rides',
        tags: ['Viajes'],
        summary: 'Obtener todos los viajes disponibles',
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'origin_lat', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'origin_lng', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'radius', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Viajes disponibles obtenidos exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener los viajes',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['origin_lat', 'origin_lng', 'radius', 'date']);
            $rides = $this->rideRequestService->getAvailableRides($filters);

            return $this->successResponse(
                new RideRequestCardResourceCollection($rides),
                'Viajes disponibles obtenidos exitosamente'
            );
        } catch (\Exception $e) {
            Log::error('Error en index: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener los viajes', 500);
        }
    }

    #[OA\Post(
        path: '/api/rides',
        tags: ['Viajes'],
        summary: 'Crear un nuevo viaje',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateRideRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Viaje creado exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al crear el viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                // Origen
                'origin_address' => 'required|string|max:255',
                'origin_lat' => 'required|numeric|between:-90,90',
                'origin_lng' => 'required|numeric|between:-180,180',
                'origin_city' => 'nullable|string|max:255',
                'origin_state' => 'nullable|string|max:255',
                'origin_country_id' => 'nullable|integer|exists:countries,id',
                'origin_state_id' => 'nullable|integer|exists:states,id',
                'origin_city_id' => 'nullable|integer|exists:cities,id',

                // Destino
                'destination_address' => 'required|string|max:255',
                'destination_lat' => 'required|numeric|between:-90,90',
                'destination_lng' => 'required|numeric|between:-180,180',
                'destination_city' => 'nullable|string|max:255',
                'destination_state' => 'nullable|string|max:255',
                'destination_country_id' => 'nullable|integer|exists:countries,id',
                'destination_state_id' => 'nullable|integer|exists:states,id',
                'destination_city_id' => 'nullable|integer|exists:cities,id',

                // Viaje
                'departure_time' => 'required|date|after:now',
                'available_seats' => 'required|integer|min:1|max:8',
                'price_per_seat' => 'required|numeric|min:0',
                'notes' => 'nullable|string',

                // Vehículo (opcional)
                'vehicle_brand' => 'nullable|string|max:255',
                'vehicle_model' => 'nullable|string|max:255',
                'vehicle_year' => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
                'vehicle_color' => 'nullable|string|max:255',
            ]);

            $rideRequest = $this->rideRequestService->createRide(
                $request->user(),
                $validated
            );

            return $this->successResponse(
                $rideRequest,
                'Viaje creado exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creando ride: ' . $e->getMessage());
            return $this->errorResponse('Error al crear el viaje', 500);
        }
    }

    #[OA\Get(
        path: '/api/rides/{id}',
        tags: ['Viajes'],
        summary: 'Obtener detalle de un viaje por ID',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Viaje no encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener el viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $rideRequest = $this->rideRequestService->findRide($id, [
                'driver:id,name,rating,completed_jobs',
                'driver.about:user_id,avatar,phone',
                'passengers:id,name',
                'passengers.about:user_id,avatar',
                'originCity:id,name',
                'destinationCity:id,name',
            ]);
            return $this->successResponse(
                new RideRequestDetailsResource($rideRequest),
                'Detalle del viaje'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Viaje no encontrado');
        } catch (\Exception $e) {
            Log::error('Error en show: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return $this->errorResponse('Error al obtener el viaje', 500);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/join',
        tags: ['Viajes'],
        summary: 'Unirse a un viaje como pasajero',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/JoinRideRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Te has unido al viaje exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al unirse al viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 404, description: 'Viaje no encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al unirse al viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function joinRide(Request $request, int $id): JsonResponse
    {
        try {

            $rideRequest = $this->rideRequestService->findRide($id);

            $validated = $request->validate([
                'seats' => 'required|integer|min:1|max:' . $rideRequest->available_seats,
                'pickup_location' => 'nullable|string',
                'dropoff_location' => 'nullable|string',
                'special_requests' => 'nullable|string',
            ]);
            $this->rideRequestService->joinRide(
                $rideRequest,
                $request->user(),
                $validated
            );

            return $this->successResponse(
                null,
                'Te has unido al viaje exitosamente'
            );
        } catch (InsufficientSeatsException | CannotJoinOwnRideException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Viaje no encontrado');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Error al unirse al viaje: ' . $e->getMessage());
            return $this->errorResponse('Error al unirse al viaje', 500);
        }
    }

    #[OA\Get(
        path: '/api/rides/my-rides',
        tags: ['Viajes'],
        summary: 'Obtener viajes del usuario autenticado (legacy)',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tus viajes obtenidos exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener tus viajes',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function myRides(Request $request): JsonResponse
    {
        try {
            $rides = $this->rideRequestService->getUserRides($request->user());
            return $this->successResponse($rides, 'Tus viajes obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tus viajes', 500);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/passengers/{passengerId}/confirm',
        tags: ['Viajes'],
        summary: 'Confirmar un pasajero en el viaje',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'passengerId', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pasajero confirmado',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al confirmar pasajero',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function confirmPassenger(Request $request, int $id, int $passengerId): JsonResponse
    {
        try {
            $ride = $this->rideRequestService->findRide($id);
            $this->rideRequestService->confirmPassenger($ride, $passengerId, $request->user());

            return $this->successResponse(null, 'Pasajero confirmado');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/start',
        tags: ['Viajes'],
        summary: 'Iniciar un viaje',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Viaje iniciado',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al iniciar el viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function start(Request $request, int $id): JsonResponse
    {
        try {
            $ride = $this->rideRequestService->findRide($id);
            $this->rideRequestService->startRide($ride, $request->user());

            return $this->successResponse(null, 'Viaje iniciado');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/pickup/{passengerId}',
        tags: ['Viajes'],
        summary: 'Marcar pasajero como recogido',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'passengerId', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pasajero recogido',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al recoger pasajero',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function pickupPassenger(Request $request, int $id, int $passengerId): JsonResponse
    {
        try {
            $ride = $this->rideRequestService->findRide($id);
            $this->rideRequestService->markPickedUp($ride, $passengerId, $request->user());

            return $this->successResponse(null, 'Pasajero recogido');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/dropoff/{passengerId}',
        tags: ['Viajes'],
        summary: 'Marcar pasajero como dejado en destino',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'passengerId', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pasajero dejado en destino',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al dejar pasajero',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function dropoffPassenger(Request $request, int $id, int $passengerId): JsonResponse
    {
        try {
            $ride = $this->rideRequestService->findRide($id);
            $this->rideRequestService->markDroppedOff($ride, $passengerId, $request->user());

            return $this->successResponse(null, 'Pasajero dejado en destino');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/complete',
        tags: ['Viajes'],
        summary: 'Completar un viaje',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Viaje completado',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al completar el viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $ride = $this->rideRequestService->findRide($id);
            $this->rideRequestService->completeRide($ride, $request->user());

            return $this->successResponse(null, 'Viaje completado');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/cancel',
        tags: ['Viajes'],
        summary: 'Cancelar un viaje',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'reason', type: 'string', description: 'Motivo de cancelación'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cancelado correctamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 400, description: 'Error al cancelar el viaje',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $ride = $this->rideRequestService->findRide($id);
            $validated = $request->validate(['reason' => 'nullable|string']);

            $this->rideRequestService->cancelRide($ride, $request->user(), $validated['reason'] ?? null);

            return $this->successResponse(null, 'Cancelado correctamente');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Post(
        path: '/api/rides/{id}/rate',
        tags: ['Viajes'],
        summary: 'Calificar un viaje',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'target_user_id', type: 'integer', description: 'ID del usuario a calificar'),
                new OA\Property(property: 'rating', type: 'integer', description: 'Calificación del 1 al 5'),
                new OA\Property(property: 'comment', type: 'string', description: 'Comentario opcional'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Calificación guardada',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 400, description: 'Error al calificar',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function rate(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'target_user_id' => 'required|exists:users,id',
                'rating' => 'required|integer|between:1,5',
                'comment' => 'nullable|string|max:1000',
            ]);

            $this->rideRequestService->rateRide(
                $this->rideRequestService->findRide($id),
                $request->user(),
                $validated
            );

            return $this->successResponse(null, 'Calificación guardada');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Get(
        path: '/api/rides/my-rides',
        tags: ['Viajes'],
        summary: 'Obtener viajes del usuario autenticado (publicaciones)',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tus solicitudes obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener tus solicitudes',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function myRidesPublication(MyRidesRequest $request): JsonResponse
    {
        try {
            $user   = $request->user();
            $result = $this->publicationService->getMyRides($user, $request);
 
            $dataResponse = [
                'rides'  => $result['rides'],
                'stats' => $result['stats'],
                'meta'  => [
                    'current_page' => $result['rides']->currentPage(),
                    'last_page'    => $result['rides']->lastPage(),
                    'per_page'     => $result['rides']->perPage(),
                    'total'        => $result['rides']->total(),
                ],
            ];
            return $this->successResponse($dataResponse, 'Tus solicitudes obtenidas exitosamente');
        } catch (\Exception $e) {
            Log::error('Error en myRidesPublication: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener tus solicitudes', 500);
        }
    }


    #[OA\Get(
        path: '/api/rides/stats',
        tags: ['Viajes'],
        summary: 'Obtener estadísticas del conductor',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener estadísticas',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function stats(DriverStatsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
     
            $stats = $this->rideRequestService->getStats(
                user:          $user,
                period:        $request->period(),
                dateRange:     $request->dateRange(),
                previousRange: $request->previousDateRange(),
            );

            $dataResponse = DriverStatsResource::make($stats);
     
            return $this->successResponse($dataResponse, 'Estadisticas obtenidas exitosamente');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }  catch (\Exception $e) {
            Log::error('Error en myRidesPublication: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener tus solicitudes', 500);
        }
    }
}

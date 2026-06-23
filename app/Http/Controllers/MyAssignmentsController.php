<?php

namespace App\Http\Controllers;

use App\Services\MyAssignmentsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MyAssignmentsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private MyAssignmentsService $myAssignments
    ) {}

    #[OA\Get(
        path: '/api/my-assignments',
        tags: ['Perfil'],
        summary: 'Obtener todas las asignaciones del usuario autenticado',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Asignaciones obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener asignaciones',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * Obtener TODAS las asignaciones del usuario autenticado
     * Incluye: servicios donde es worker + viajes donde es conductor o pasajero
     */
    public function index(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->index());
    }

    #[OA\Get(
        path: '/api/my-assignments/services',
        tags: ['Perfil'],
        summary: 'Obtener servicios donde el usuario es worker',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Servicios obtenidos exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener servicios',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * Obtener solo servicios donde el usuario es worker
     */
    public function services(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->services());
    }

    #[OA\Get(
        path: '/api/my-assignments/rides/driver',
        tags: ['Perfil'],
        summary: 'Obtener viajes donde el usuario es conductor',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Viajes obtenidos exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener viajes',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * Obtener solo viajes donde el usuario es conductor
     */
    public function ridesAsDriver(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->ridesAsDriver());
    }

    #[OA\Get(
        path: '/api/my-assignments/rides/passenger',
        tags: ['Perfil'],
        summary: 'Obtener viajes donde el usuario es pasajero',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Viajes obtenidos exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener viajes',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * Obtener solo viajes donde el usuario es pasajero
     */
    public function ridesAsPassenger(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->ridesAsPassenger());
    }
}

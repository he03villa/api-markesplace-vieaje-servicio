<?php

namespace App\Http\Controllers;

use App\Services\MyAssignmentsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyAssignmentsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private MyAssignmentsService $myAssignments
    ) {}

    /**
     * Obtener TODAS las asignaciones del usuario autenticado
     * Incluye: servicios donde es worker + viajes donde es conductor o pasajero
     */
    public function index(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->index());
    }

    /**
     * Obtener solo servicios donde el usuario es worker
     */
    public function services(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->services());
    }

    /**
     * Obtener solo viajes donde el usuario es conductor
     */
    public function ridesAsDriver(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->ridesAsDriver());
    }

    /**
     * Obtener solo viajes donde el usuario es pasajero
     */
    public function ridesAsPassenger(): JsonResponse
    {
        return $this->successResponse($this->myAssignments->ridesAsPassenger());
    }
}

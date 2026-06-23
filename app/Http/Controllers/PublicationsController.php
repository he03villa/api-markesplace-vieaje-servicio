<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExplorePublicationsRequest;
use App\Http\Resources\PublicationCollection;
use App\Http\Resources\PublicationExploreResource;
use App\Http\Resources\PublicationResource;
use App\Services\PublicationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class PublicationsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PublicationService $publicationService
    ) {}

    #[OA\Get(
        path: '/api/my-publications',
        tags: ['Perfil'],
        summary: 'Obtener publicaciones del usuario autenticado',
        security: [['jwt' => []]],
        parameters: [
            new OA\QueryParameter(name: 'category', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Publicaciones obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener las publicaciones',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * GET /api/my-publications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['category', 'status', 'search', 'per_page']);
            
            $publications = $this->publicationService->getUserPublications(
                $request->user(),
                $filters
            );

            // Usar el Resource Collection
            return $this->successResponse(
                new PublicationCollection($publications),
                'Publicaciones obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error en my-publications: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener las publicaciones', 500);
        }
    }

    #[OA\Get(
        path: '/api/my-publications/stats',
        tags: ['Perfil'],
        summary: 'Obtener estadísticas de publicaciones',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener estadísticas',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * GET /api/my-publications/stats
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->publicationService->getUserStats($request->user());
            
            return $this->successResponse([
                'services_active' => $stats['services']['active'],
                'services_total' => $stats['services']['total'],
                'rides_active' => $stats['rides']['active'],
                'rides_total' => $stats['rides']['total'],
                'tabs' => [
                    ['id' => 'all', 'label' => 'Todos', 'count' => $stats['services']['total'] + $stats['rides']['total']],
                    ['id' => 'service', 'label' => 'Servicios', 'count' => $stats['services']['total']],
                    ['id' => 'ride', 'label' => 'Viajes', 'count' => $stats['rides']['total']],
                ]
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', 500);
        }
    }

    /**
     * GET /api/my-publications/{publication}
     * (Opcional: para ver detalle individual)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $publication = $request->user()
                ->publications()
                ->with('publishable')
                ->findOrFail($id);

            return $this->successResponse(
                new PublicationResource($publication),
                'Publicación obtenida exitosamente'
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Publicación no encontrada');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la publicación', 500);
        }
    }

    #[OA\Get(
        path: '/api/my-publications/summary',
        tags: ['Perfil'],
        summary: 'Obtener resumen de publicaciones',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Resumen obtenido exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener resumen',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->publicationService->getSummary($request);
            return $this->successResponse($summary, 'Resumen obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener resumen', 500);
        }
    }

    #[OA\Get(
        path: '/api/my-publications/explore',
        tags: ['Perfil'],
        summary: 'Explorar publicaciones disponibles',
        security: [['jwt' => []]],
        parameters: [
            new OA\QueryParameter(name: 'category', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Publicaciones obtenidas exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener publicaciones',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function explore(ExplorePublicationsRequest $request): JsonResponse
    {
        try {
            $publications = $this->publicationService->explore($request);
            $dataResponse = [
                'publications' => PublicationExploreResource::collection($publications),
                'pagination' => [
                    'current_page' => $publications->currentPage(),
                    'last_page' => $publications->lastPage(),
                    'per_page' => $publications->perPage(),
                    'total' => $publications->total(),
                    'has_more_pages' => $publications->hasMorePages(),
                ],
            ];
            return $this->successResponse(
                $dataResponse,
                'Publicaciones obtenidas exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener publicaciones', 500);
        }
    }
}

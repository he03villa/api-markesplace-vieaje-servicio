<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Services\ProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    #[OA\Get(
        path: '/api/profile',
        tags: ['Perfil'],
        summary: 'Obtener perfil del usuario autenticado',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Perfil obtenido exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 404, description: 'Perfil no encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al obtener el perfil',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * GET /api/profile
     */
    public function show(): JsonResponse
    {
        try {
            $user = $this->profileService->getProfile(auth('api')->id());
            return $this->successResponse(
                new ProfileResource($user),
                'Perfil obtenido exitosamente'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Perfil no encontrado');  
        } catch (\Exception $e) {
            Log::error('Error en my-publications: ' . $e->getMessage());
            return $this->errorResponse('Error al obtener el perfil', 500);
        }
    }

    #[OA\Put(
        path: '/api/profile',
        tags: ['Perfil'],
        summary: 'Actualizar perfil del usuario autenticado',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'phone', type: 'string'),
                        new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                        new OA\Property(property: 'bio', type: 'string'),
                        new OA\Property(property: 'address', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Perfil actualizado exitosamente',
                content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
            new OA\Response(response: 500, description: 'Error al actualizar el perfil',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    /**
     * PUT /api/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            // validated() ya trae solo los campos que pasaron las reglas
            $data = $request->validated();
    
            if ($request->hasFile('avatar')) {
                $data['avatar_file'] = $request->file('avatar');
                unset($data['avatar']);
            }
    
            $user = $this->profileService->updateProfile(
                auth('api')->user(),
                $data
            );

            return $this->successResponse(
                new ProfileResource($user),
                'Perfil actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Error en my-publications: ' . $e->getMessage());
            return $this->errorResponse('Error al actualizar el perfil', 500);
        }
    }
}

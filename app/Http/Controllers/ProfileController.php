<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Services\ProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProfileService $profileService
    ) {}

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

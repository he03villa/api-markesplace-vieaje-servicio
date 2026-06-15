<?php

namespace App\Http\Controllers;

use App\Services\UserProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserProfileService $userProfileService
    ) {}

    public function show(Request $request, int $userId = null): JsonResponse
    {
        try {
            $targetUserId = $userId ?? $request->user()->id;
            $isOwner = $request->user()->id === $targetUserId;
            
            $user = $this->userProfileService->getUserProfile($targetUserId);
            
            if (!$user) {
                return $this->notFoundResponse('Usuario no encontrado');
            }

            $responseData = $this->formatUserResponse($user, $isOwner);

            return $this->successResponse($responseData, 'Perfil obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el perfil: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateProfileUpdate($request);
            
            $user = $this->userProfileService->updateUserProfile($request->user(), $validated);

            return $this->successResponse(
                $this->formatUserResponse($user, true),
                'Perfil actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el perfil: ' . $e->getMessage(), 500);
        }
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        try {
            $success = $this->userProfileService->removeAvatar($request->user());
            
            if (!$success) {
                return $this->errorResponse('No se encontró avatar para eliminar', 400);
            }

            return $this->successResponse(
                null,
                'Avatar eliminado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el avatar', 500);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->loadCount([
                'serviceRequests',
                'offers',
                'reviews',
                'rideRequests',
                'ridePassengers'
            ]);

            $stats = [
                'total_requests' => $user->service_requests_count,
                'total_offers' => $user->offers_count,
                'total_reviews' => $user->reviews_count,
                'total_rides_as_driver' => $user->ride_requests_count,
                'total_rides_as_passenger' => $user->ride_passengers_count,
                'completed_jobs' => $user->completed_jobs,
                'rating' => $user->rating,
                'member_since' => $user->created_at->format('Y-m-d'),
                'account_age_days' => $user->created_at->diffInDays(now()),
            ];

            return $this->successResponse($stats, 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener estadísticas', 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|string|email',
                'phone' => 'nullable|string',
                'min_rating' => 'nullable|numeric|min:0|max:5',
                'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
                'order_by' => 'nullable|in:name,rating,completed_jobs,created_at',
                'order_direction' => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $users = $this->userProfileService->searchUsers(
                $validated,
                $validated['per_page'] ?? 20
            );

            return $this->successResponse(
                $users,
                'Usuarios encontrados exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error en la búsqueda', 500);
        }
    }

    private function validateProfileUpdate(Request $request): array
    {
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $request->user()->id,
            'phone' => 'nullable|string|max:20|unique:user_abouts,phone,' . $request->user()->id . ',user_id',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'bio' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'occupation' => 'nullable|string|max:255',
            'education' => 'nullable|string|max:255',
            'interests' => 'nullable|json',
            'languages' => 'nullable|json',
            'social_links' => 'nullable|json',
        ];

        if ($request->has('password')) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        return $request->validate($rules);
    }

    private function formatUserResponse($user, bool $isOwner = false): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $isOwner ? $user->email : null,
            'phone' => $isOwner ? $user->phone : null,
            'phone_formatted' => $isOwner && $user->about ? $user->about->formatted_phone : null,
            'avatar' => $user->avatar_url,
            'rating' => $user->rating,
            'completed_jobs' => $user->completed_jobs,
            'member_since' => $user->created_at->format('Y-m-d'),
            'about' => $this->formatAboutInfo($user->about, $isOwner),
            'stats' => [
                'total_requests' => $user->service_requests_count ?? 0,
                'total_offers' => $user->offers_count ?? 0,
                'total_reviews' => $user->reviews_count ?? 0,
                'total_rides_as_driver' => $user->ride_requests_count ?? 0,
                'total_rides_as_passenger' => $user->ride_passengers_count ?? 0,
            ],
        ];
    }

    private function formatAboutInfo($about, bool $isOwner): ?array
    {
        if (!$about) {
            return null;
        }

        return [
            'bio' => $about->bio,
            'address' => $about->address,
            'birth_date' => $about->birth_date?->format('Y-m-d'),
            'birth_age' => $about->birth_date ? $about->birth_date->diffInYears(now()) : null,
            'gender' => $about->gender,
            'occupation' => $about->occupation,
            'education' => $about->education,
            'interests' => $about->interests,
            'languages' => $about->languages,
            'social_links' => $isOwner ? $about->social_links : null,
        ];
    }
}

<?php
namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Utils\ImageUploader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ServiceRequestService
{
    public function getAvailableRequests(array $filters): LengthAwarePaginator
    {
        $query = ServiceRequest::with(['user', 'offers'])
            ->where('status', 'open');

        // Filtro por categoría
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Si hay coordenadas, calcular distancia para todos
        if (!empty($filters['lat']) && !empty($filters['lng'])) {
            $radius = $filters['radius'] ?? 10;
            $lat = $filters['lat'];
            $lng = $filters['lng'];

            $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
            
            $query->selectRaw("*, {$haversine} AS distance", [$lat, $lng, $lat]);
            
            // Ordenar: primero los cercanos (con distancia), luego los remotos
            $query->orderByRaw("CASE WHEN {$haversine} <= ? THEN 0 ELSE 1 END, distance ASC", [$lat, $lng, $lat, $radius]);
        } else {
            $query->selectRaw("*, 'Remoto' AS distance")->latest();
        }

        return $query->paginate(20);
    }

    public function createRequest(User $user, array $data): ServiceRequest
    {
        $images = $data['images'] ?? null;
        unset($data['images']);
        $request = $user->serviceRequests()->create($data);
        Log::info("service request created: " . $request->id);
        if ($images) {
            $dataImages = ImageUploader::storeMultiple(
                $images,                   // array de UploadedFile
                'service-requests',        // folder base
                $request->id               // subfolder opcional
            );
            $request->update(['images' => $dataImages]);
            Log::info("service request images updated: " . $request->id);
        }
        return $request->fresh();
    }

    public function updateRequest(ServiceRequest $serviceRequest, array $data): ServiceRequest
    {
        if (isset($data['images'])) {
            // Opcional: eliminar imágenes antiguas si se reemplazan
            if (isset($data['replace_images']) && $data['replace_images']) {
                ImageUploader::deleteMultiple($serviceRequest->images ?? []);
            }
            
            $data['images'] = ImageUploader::storeMultiple(
                $data['images'],                  // array de UploadedFile
                'service-requests',               // folder base
                $serviceRequest->id               // subfolder opcional
            );
        }
        $serviceRequest->update($data);
        return $serviceRequest->fresh();
    }

    public function deleteRequest(ServiceRequest $request): bool
    {
        if (!empty($request->images)) {
            \App\Utils\ImageUploader::deleteMultiple($request->images);
        }

        return $request->delete();
    }

    public function getUserRequests(User $user): LengthAwarePaginator
    {
        return $user->serviceRequests()
            ->with('offers')
            ->latest()
            ->paginate(20);
    }

    public function findRequest(int $id, $with = []): ServiceRequest
    {
        if (count($with) > 0) {
            return ServiceRequest::with($with)->findOrFail($id);
        }
        return ServiceRequest::findOrFail($id);
    }
}
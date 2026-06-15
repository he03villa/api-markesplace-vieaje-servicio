<?php
// app/Services/UserProfileService.php (REVISADO)
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserProfileService
{
    public function updateUserProfile(User $user, array $data): User
    {
        // Separar datos del usuario y datos del about
        $userData = $this->extractUserData($data);
        $aboutData = $this->extractAboutData($data);
        
        // Actualizar usuario
        if (!empty($userData)) {
            $user->update($userData);
        }
        
        // Manejar avatar si está presente
        if (isset($data['avatar']) && $data['avatar']->isValid()) {
            $aboutData['avatar'] = $this->handleAvatarUpload($user, $data['avatar']);
        }
        
        // Actualizar o crear información about
        if (!empty($aboutData)) {
            $user->about()->updateOrCreate(['user_id' => $user->id], $aboutData);
        }
        
        return $user->load('about');
    }

    public function getUserProfile(int $userId, bool $withStats = true): ?User
    {
        $query = User::with([
            'about',
            'reviews.reviewer',
            'givenReviews.reviewedUser'
        ]);
        
        if ($withStats) {
            $query->withCount([
                'serviceRequests',
                'offers',
                'reviews',
                'rideRequests',
                'ridePassengers'
            ]);
        }
        
        return $query->find($userId);
    }

    public function searchUsers(array $filters, int $perPage = 20)
    {
        $query = User::with('about');
        
        // Filtros
        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        
        if (!empty($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }
        
        if (!empty($filters['phone'])) {
            $query->whereHas('about', function ($q) use ($filters) {
                $q->where('phone', 'like', '%' . $filters['phone'] . '%');
            });
        }
        
        if (!empty($filters['min_rating'])) {
            $query->where('rating', '>=', $filters['min_rating']);
        }
        
        if (!empty($filters['gender'])) {
            $query->whereHas('about', function ($q) use ($filters) {
                $q->where('gender', $filters['gender']);
            });
        }
        
        // Ordenamiento
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);
        
        return $query->paginate($perPage);
    }

    private function extractUserData(array $data): array
    {
        $allowedKeys = ['name', 'email', 'password'];
        return array_intersect_key($data, array_flip($allowedKeys));
    }

    private function extractAboutData(array $data): array
    {
        $allowedKeys = [
            'phone', 'bio', 'address', 'birth_date', 'gender',
            'occupation', 'education', 'interests', 'languages', 'social_links'
        ];
        
        $aboutData = array_intersect_key($data, array_flip($allowedKeys));
        
        // Procesar campos JSON
        foreach (['interests', 'languages', 'social_links'] as $jsonField) {
            if (isset($aboutData[$jsonField]) && is_string($aboutData[$jsonField])) {
                $decoded = json_decode($aboutData[$jsonField]);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $aboutData[$jsonField] = $decoded;
                }
            }
        }
        
        return $aboutData;
    }

    private function handleAvatarUpload(User $user, $avatarFile): string
    {
        // Eliminar avatar anterior si existe
        if ($user->about && $user->about->avatar) {
            $this->deleteAvatar($user->about->avatar);
        }
        
        // Generar nombre único para el archivo
        $extension = $avatarFile->getClientOriginalExtension();
        $filename = Str::slug($user->name) . '_' . time() . '.' . $extension;
        
        // Guardar en storage
        $path = $avatarFile->storeAs('avatars', $filename, 'public');
        
        return $path;
    }

    private function deleteAvatar(string $avatarPath): void
    {
        // Eliminar del storage si no es una URL externa
        if (!filter_var($avatarPath, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($avatarPath);
        }
    }

    public function removeAvatar(User $user): bool
    {
        if (!$user->about || !$user->about->avatar) {
            return false;
        }
        
        $this->deleteAvatar($user->about->avatar);
        $user->about->update(['avatar' => null]);
        
        return true;
    }
}
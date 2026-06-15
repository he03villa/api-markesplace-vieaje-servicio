<?php

namespace App\Services;

use App\Models\User;
use App\Utils\ImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileService {
    /**
     * Retorna el usuario autenticado con sus relaciones cargadas.
     */
    public function getProfile(int $userId): User
    {
        return User::with(['about', 'reviews', 'offers', 'rideRequests', 'ridePassengers'])
            ->findOrFail($userId);
    }

    /**
     * Actualiza nombre, título (occupation), bio y avatar.
     */
    public function updateProfile(User $user, array $data): User
    {
        // Campos del modelo User
        if (isset($data['name'])) {
            $user->update(['name' => $data['name']]);
        }

        // Campos del modelo UserAbout
        $aboutFields = array_filter([
            'occupation' => $data['title']  ?? null,
            'bio'        => $data['bio']    ?? null,
            'phone'      => $data['phone']  ?? null,
            'address'    => $data['location'] ?? null,
        ], fn($v) => !is_null($v));

        // Avatar: puede venir como URL o como archivo subido
        if (isset($data['avatar_url'])) {
            $aboutFields['avatar'] = $data['avatar_url'];
        }

        if (isset($data['avatar_file'])) {
            $oldAvatar = $user->about?->avatar;
            if ($oldAvatar && Storage::disk('public')->exists($oldAvatar)) {
                Storage::disk('public')->delete($oldAvatar);
            }
            $aboutFields['avatar'] = ImageUploader::store(
                $data['avatar_file'],
                'avatars',
                $user->id
            );
        }

        if (!empty($aboutFields)) {
            $user->about()->updateOrCreate(
                ['user_id' => $user->id],
                $aboutFields
            );
        }

        // Recargar relaciones para devolver datos frescos
        return $user->load(['about', 'reviews', 'offers']);
    }
}
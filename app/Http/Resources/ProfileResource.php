<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user  = $this->resource;
        $about = $user->about;

        return [
            'id'              => $user->id,
            'name'            => $user->name,

            // ✅ Usa el accessor de UserAbout que ya maneja storage/ vs URL externa
            'avatar'          => $about?->avatar_url ?? $user->avatar_url,
            'has_notification' => $user->has_notification,

            'title'           => $about?->occupation  ?? 'Sin título',
            'bio'             => $about?->bio          ?? '',
            'verified'        => (bool) $user->email_verified_at,
            'rating'          => round((float) $user->rating, 1),
            'review_count'    => $user->count_reviews,
            'member_since'    => $user->created_at->format('Y'),

            // ✅ Usa el accessor de UserAbout que formatea el teléfono
            'phone'           => $about?->formatted_phone,

            'location'        => $about?->address   ?? 'Sin ubicación',
            'response_time'   => '15 min',
            'completion_rate' => $this->completionRate($user),

            'stats' => $this->buildStats($user),

            // ✅ interests ya viene como array gracias al cast del modelo
            'skills' => $about?->interests ?? [],

            'total_trips'     => $user->total_trips,
            'total_earned'    => $user->total_earned,

            'verifications' => $this->buildVerifications($user, $about),

            'activities' => ActivityResource::collection(
                $this->latestActivities($user)
            ),

            'menu_items' => $this->menuItems(),
        ];
    }

    // ─── Stats ──────────────────────────────────────────────────────────────

    private function buildStats(User $user): array
    {
        return [
            [
                'label'  => 'Servicios',
                'value'  => (string) ($user->completed_jobs ?? 0),
                'suffix' => null,
                'icon'   => 'briefcase-outline',
                'color'  => '#6366f1',
            ],
            [
                'label'  => 'Rating',
                'value'  => number_format((float) $user->rating, 1),
                'suffix' => '/5',
                'icon'   => 'star',
                'color'  => '#f59e0b',
            ],
            [
                'label'  => 'Experiencia',
                'value'  => (string) max(1, now()->year - $user->created_at->year),
                'suffix' => 'años',
                'icon'   => 'calendar-outline',
                'color'  => '#10b981',
            ],
            [
                'label'  => 'Respuesta',
                'value'  => '15',
                'suffix' => 'min',
                'icon'   => 'flash-outline',
                'color'  => '#ec4899',
            ],
        ];
    }

    // ─── Verifications ──────────────────────────────────────────────────────

    private function buildVerifications(User $user, ?object $about): array
    {
        return [
            [
                'type'     => 'identity',
                'label'    => 'Identidad verificada',
                'verified' => (bool) $user->email_verified_at,
                'icon'     => 'shield-checkmark',
            ],
            [
                'type'     => 'phone',
                'label'    => 'Teléfono confirmado',
                // ✅ Usa el scope hasPhone lógicamente — campo no nulo = verificado
                'verified' => !empty($about?->phone),
                'icon'     => 'call',
            ],
            [
                'type'     => 'email',
                'label'    => 'Email verificado',
                'verified' => (bool) $user->email_verified_at,
                'icon'     => 'mail',
            ],
        ];
    }

    // ─── Completion rate ────────────────────────────────────────────────────

    private function completionRate(User $user): int
    {
        $about = $user->about;

        $checks = [
            !empty($user->name),
            !empty($about?->bio),
            !empty($about?->phone),
            !empty($about?->avatar),       // campo raw, no el accessor
            !empty($about?->occupation),
        ];

        $filled = count(array_filter($checks));

        return (int) round(($filled / count($checks)) * 100);
    }

    // ─── Activities ─────────────────────────────────────────────────────────

    private function latestActivities(User $user): \Illuminate\Support\Collection
    {
        $activities = collect();

        // Servicios completados (desde offers)
        $user->offers()
            ->where('status', 'completed')
            ->latest('updated_at')
            ->take(2)
            ->get()
            ->each(function ($offer) use ($activities) {
                $activities->push((object) [
                    'id'     => 'offer-' . $offer->id,
                    'type'   => 'completed',
                    'title'  => 'Servicio completado',
                    'time'   => $offer->updated_at->diffForHumans(),
                    'icon'   => 'checkmark-done',
                    'color'  => '#10b981',
                    'accent' => 'rgba(16,185,129,0.15)',
                ]);
            });

        // Reseñas recibidas
        $user->reviews()
            ->latest()
            ->take(2)
            ->get()
            ->each(function ($review) use ($activities) {
                $activities->push((object) [
                    'id'     => 'review-' . $review->id,
                    'type'   => 'review',
                    'title'  => "Nueva reseña {$review->rating} estrellas",
                    'time'   => $review->created_at->diffForHumans(),
                    'icon'   => 'star',
                    'color'  => '#f59e0b',
                    'accent' => 'rgba(245,158,11,0.15)',
                ]);
            });

        return $activities
            ->sortByDesc(fn($a) => $a->time)
            ->take(4)
            ->values();
    }

    // ─── Menu items ─────────────────────────────────────────────────────────

    private function menuItems(): array
    {
        return [
            [
                'icon'        => 'briefcase-outline',
                'title'       => 'Mis servicios',
                'description' => 'Gestiona tus publicaciones activas',
                'route'       => '/my-services',
                'color'       => '#6366f1',
                'bg'          => 'rgba(99,102,241,0.1)',
            ],
            [
                'icon'        => 'car-outline',
                'title'       => 'Mis viajes',
                'description' => 'Historial y viajes programados',
                'route'       => '/my-rides',
                'color'       => '#10b981',
                'bg'          => 'rgba(16,185,129,0.1)',
            ],
            [
                'icon'        => 'star-outline',
                'title'       => 'Reseñas',
                'description' => 'Ver todas las reseñas recibidas',
                'route'       => '/reviews',
                'color'       => '#f59e0b',
                'bg'          => 'rgba(245,158,11,0.1)',
            ],
            [
                'icon'        => 'trending-up-outline',
                'title'       => 'Estadísticas',
                'description' => 'Análisis de rendimiento',
                'route'       => '/stats',
                'color'       => '#ec4899',
                'bg'          => 'rgba(236,72,153,0.1)',
            ],
            [
                'icon'        => 'settings-outline',
                'title'       => 'Configuración',
                'description' => 'Ajustes de cuenta y privacidad',
                'route'       => '/settings',
                'color'       => '#6b7280',
                'bg'          => 'rgba(107,114,128,0.1)',
            ],
            [
                'icon'        => 'help-circle-outline',
                'title'       => 'Ayuda y soporte',
                'description' => 'Centro de ayuda y contacto',
                'route'       => '/help',
                'color'       => '#3b82f6',
                'bg'          => 'rgba(59,130,246,0.1)',
            ],
        ];
    }
}

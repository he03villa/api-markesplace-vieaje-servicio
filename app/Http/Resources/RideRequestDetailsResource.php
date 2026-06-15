<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class RideRequestDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isDriver = $user && $this->driver_id === $user->id;

        // Datos del pasajero actual si aplica
        $myPivot = $user ? $this->passengers()->where('user_id', $user->id)->first()?->pivot : null;

        return [
            'id' => $this->id,
            'status' => $this->status,

            // Contexto según rol
            'meta' => [
                'is_driver' => $isDriver,
                'is_passenger' => (bool) $myPivot,
                'can_join' => !$isDriver && !$myPivot && in_array($this->status, ['available', 'full']) && $this->hasAvailableSeats(1),
                'can_start' => $isDriver && in_array($this->status, ['available', 'full']),
                'can_complete' => $isDriver && $this->status === 'in_progress',
                'can_cancel' => $this->canBeCancelledBy($user),
                'my_status' => $myPivot?->status,
                'my_seats' => (int) ($myPivot?->seats_reserved ?? 0),
            ],

            // Ruta completa
            'route' => [
                'origin' => [
                    'address' => $this->origin_address,
                    'city' => $this->originCity?->name ?? $this->origin_city,
                    'state' => $this->originState?->name ?? $this->origin_state,
                    'country' => $this->originCountry?->name,
                    'lat' => (float) $this->origin_lat,
                    'lng' => (float) $this->origin_lng,
                ],
                'destination' => [
                    'address' => $this->destination_address,
                    'city' => $this->destinationCity?->name ?? $this->destination_city,
                    'state' => $this->destinationState?->name ?? $this->destination_state,
                    'country' => $this->destinationCountry?->name,
                    'lat' => (float) $this->destination_lat,
                    'lng' => (float) $this->destination_lng,
                ],
                'distance_km' => $this->estimated_distance,
            ],

            // Horarios
            'schedule' => [
                'departure_time' => $this->departure_time,
                'departure_human' => $this->departure_time?->translatedFormat('d M Y, h:i A'),
                'started_at' => $this->started_at,
                'completed_at' => $this->completed_at,
                'is_past' => $this->departure_time?->isPast(),
            ],

            // Conductor
            'driver' => $this->when($this->driver, function () use ($isDriver, $myPivot) {
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                    'avatar' => $this->driver->avatar_url,
                    'rating' => (float) $this->driver->rating,
                    'completed_jobs' => (int) $this->driver->completed_jobs,
                    'phone' => $this->when($isDriver || $myPivot?->status === 'confirmed', $this->driver->phone),
                ];
            }),

            // Vehículo
            'vehicle' => [
                'make' => $this->vehicle_make,
                'model' => $this->vehicle_model,
                'year' => $this->vehicle_year,
                'color' => $this->vehicle_color,
                'display' => trim("{$this->vehicle_make} {$this->vehicle_model} {$this->vehicle_year}"),
            ],

            // Asientos y precio
            'seats' => [
                'available' => (int) $this->available_seats,
                'total' => (int) $this->total_seats,
                'price_per_seat' => (float) $this->price_per_seat,
                'price_formatted' => '$' . number_format($this->price_per_seat, 0),
            ],

            // Pasajeros (solo conductor ve detalle; pasajero ve conteo)
            'passengers' => $this->when($isDriver, function () {
                return $this->passengers->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'avatar' => $p->avatar_url,
                    'seats' => (int) $p->pivot->seats_reserved,
                    'status' => $p->pivot->status,
                    'payment_status' => $p->pivot->payment_status,
                    'pickup_location' => $p->pivot->pickup_location,
                    'special_requests' => $p->pivot->special_requests,
                    'confirmed_at' => $p->pivot->confirmed_at,
                    'picked_up_at' => $p->pivot->picked_up_at,
                    'dropped_off_at' => $p->pivot->dropped_off_at,
                    'actions' => [
                        'can_confirm' => $p->pivot->status === 'pending',
                        'can_pickup' => $p->pivot->status === 'confirmed',
                        'can_dropoff' => $p->pivot->status === 'picked_up',
                    ],
                ]);
            }),

            'passengers_summary' => [
                'count' => $this->passengers()->count(),
                'confirmed' => $this->confirmedPassengers()->count(),
                'pending' => $this->passengers()->wherePivot('status', 'pending')->count(),
            ],

            // Notas
            'notes' => $this->notes,

            // Timeline para el frontend
            'timeline' => $this->buildTimeline($myPivot, $isDriver),

            'rating' => [
                'can_rate' => $this->status === 'completed' && !$this->hasUserRated($user, $this->driver_id),
                'already_rated' => $this->hasUserRated($user, $this->driver_id),
                'my_review' => $this->when(
                    $myReview = $this->getMyReviewFor($user, $this->driver_id),
                    [
                        'rating' => $myReview?->rating,
                        'comment' => $myReview?->comment,
                    ]
                ),
            ],
        ];
    }

    private function buildTimeline(?object $pivot, bool $isDriver = false): array
    {
        $steps = [
            ['key' => 'created',    'label' => 'Publicado',           'done' => true,  'time' => $this->created_at],
            ['key' => 'confirmed',  'label' => 'Reserva confirmada',  'done' => false, 'time' => null],
            ['key' => 'started',    'label' => 'Viaje iniciado',      'done' => false, 'time' => null],
            ['key' => 'picked_up',  'label' => 'Recogido',            'done' => false, 'time' => null],
            ['key' => 'dropped_off', 'label' => 'Llegada a destino',   'done' => false, 'time' => null],
            ['key' => 'completed',  'label' => 'Completado',          'done' => false, 'time' => null],
        ];

        if ($isDriver) {
            // El conductor ve el progreso general del viaje
            // "Reserva confirmada" = cuando al menos un pasajero fue confirmado
            $firstConfirmed = $this->passengers()
                ->wherePivotNotNull('confirmed_at')
                ->orderByPivot('confirmed_at')
                ->first()?->pivot?->confirmed_at;

            if ($firstConfirmed) {
                $steps[1]['done'] = true;
                $steps[1]['time'] = $firstConfirmed;
            }
        } else {
            // El pasajero ve su propio estado en el pivot
            if ($pivot?->confirmed_at) {
                $steps[1]['done'] = true;
                $steps[1]['time'] = $pivot->confirmed_at;
            }
        }

        if ($this->started_at) {
            $steps[2]['done'] = true;
            $steps[2]['time'] = $this->started_at;
        }

        if ($isDriver) {
            // Para el conductor, picked_up y dropped_off son del viaje completo
            // Podés usar el primer/último pickup o dejarlo basado en started/completed
            $firstPickedUp = $this->passengers()
                ->wherePivotNotNull('picked_up_at')
                ->orderByPivot('picked_up_at')
                ->first()?->pivot?->picked_up_at;

            $lastDroppedOff = $this->passengers()
                ->wherePivotNotNull('dropped_off_at')
                ->orderByPivot('dropped_off_at', 'desc')
                ->first()?->pivot?->dropped_off_at;

            if ($firstPickedUp) {
                $steps[3]['done'] = true;
                $steps[3]['time'] = $firstPickedUp;
            }

            if ($lastDroppedOff) {
                $steps[4]['done'] = true;
                $steps[4]['time'] = $lastDroppedOff;
            }
        } else {
            if ($pivot?->picked_up_at) {
                $steps[3]['done'] = true;
                $steps[3]['time'] = $pivot->picked_up_at;
            }

            if ($pivot?->dropped_off_at) {
                $steps[4]['done'] = true;
                $steps[4]['time'] = $pivot->dropped_off_at;
            }
        }

        if ($this->completed_at) {
            $steps[5]['done'] = true;
            $steps[5]['time'] = $this->completed_at;
        }

        return $steps;
    }

    private function canBeCancelledBy(?object $user): bool
    {
        if (!$user) return false;
        if ($this->status === 'completed' || $this->status === 'cancelled') return false;

        $isDriver = $this->driver_id === $user->id;
        $isPassenger = $this->passengers()->where('user_id', $user->id)->exists();

        return $isDriver || $isPassenger;
    }
}

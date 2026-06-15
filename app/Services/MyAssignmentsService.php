<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\RidePassenger;
use App\Models\RideRequest;
use App\Models\ServiceRequest;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MyAssignmentsService
{
    public function index()
    {
        try {
            $user = Auth::user();

            // 1. Servicios donde el usuario tiene una oferta ACEPTADA
            $servicesAsWorker = $this->getServicesAsWorker($user->id);

            // 2. Viajes donde el usuario es conductor
            $ridesAsDriver = $this->getRidesAsDriver($user->id);

            // 3. Viajes donde el usuario es pasajero confirmado
            $ridesAsPassenger = $this->getRidesAsPassenger($user->id);

            // Combinar y ordenar por fecha (más reciente primero)
            $allAssignments = $servicesAsWorker
                ->merge($ridesAsDriver)
                ->merge($ridesAsPassenger)
                ->sortByDesc('created_at')
                ->values();

            return [
                'success' => true,
                'data' => [
                    'assignments' => $allAssignments,
                    'counts' => [
                        'total_active' => $allAssignments->whereIn('status', ['pending', 'active'])->count(),
                        'services_active' => $servicesAsWorker->where('status', 'active')->count(),
                        'trips_as_driver' => $ridesAsDriver->count(),
                        'trips_as_passenger' => $ridesAsPassenger->count(),
                    ]
                ]
            ];
        } catch (Exception $th) {
            return [
                'success' => false,
                'message' => 'Error al obtener tus asignaciones',
                'error' => $th->getMessage()
            ];
        }
    }

    public function services()
    {
        try {
            $user = Auth::user();

            $services = $this->getServicesAsWorker($user->id);

            return [
                'success' => true,
                'data' => $services
            ];
        } catch (Exception $th) {
            return [
                'success' => false,
                'message' => 'Error al obtener tus servicios',
                'error' => $th->getMessage()
            ];
        }
    }

    public function ridesAsDriver()
    {
        try {
            $user = Auth::user();

            $rides = $this->getRidesAsDriver($user->id);

            return [
                'success' => true,
                'data' => $rides
            ];
        } catch (Exception $th) {
            return [
                'success' => false,
                'message' => 'Error al obtener tus servicios',
                'error' => $th->getMessage()
            ];
        }
    }

    public function ridesAsPassenger()
    {
        try {
            $user = Auth::user();

            $rides = $this->getRidesAsPassenger($user->id);

            return [
                'success' => true,
                'data' => $rides
            ];
        } catch (Exception $th) {
            return [
                'success' => false,
                'message' => 'Error al obtener tus servicios',
                'error' => $th->getMessage()
            ];
        }
    }

    // ==========================================
    // MÉTODOS PRIVADOS DE CONSULTA
    // ==========================================

    /**
     * Servicios donde el usuario tiene una oferta ACEPTADA
     * Se busca en offers.user_id + offers.status = 'accepted'
     * Luego se carga el service_request relacionado
     */
    private function getServicesAsWorker(int $userId)
    {
        // Obtener las ofertas aceptadas del usuario
        $acceptedOffers = Offer::with(['serviceRequest.user', 'serviceRequest.offers'])
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->whereHas('serviceRequest', function ($query) {
                // Solo servicios activos (no completados ni cancelados)
                $query->whereIn('status', ['open', 'in_progress', 'delivered', 'completed']);
            })
            ->get();

        return collect($acceptedOffers->map(function (Offer $offer) {
            $service = $offer->serviceRequest;

            return [
                'id' => $service->id,
                'type' => 'service',
                'role' => 'worker',
                'status' => $this->mapServiceStatus($service->status),
                'title' => $service->title,
                'description' => $service->description,
                'price' => (float) ($offer->price ?? $service->budget_max ?? $service->budget_min),
                'address' => $service->address,
                'deadline' => $service->deadline?->toISOString(),
                'created_at' => $offer->updated_at->toISOString(), // Fecha cuando se aceptó la oferta

                // Info del dueño (cliente que publicó)
                'owner' => [
                    'id' => $service->user_id,
                    'name' => $service->user?->name,
                    'avatar' => $service->user?->avatar_url,
                    'phone' => $service->user?->phone,
                ],

                // Info de la oferta aceptada
                'offer' => [
                    'id' => $offer->id,
                    'price' => (float) $offer->price,
                    'description' => $offer->description,
                    'estimated_time' => $offer->estimated_time,
                ],

                // Metadata para UI
                'ui' => [
                    'status_label' => $this->getServiceStatusLabel($service->status),
                    'status_color' => $this->getServiceStatusColor($service->status),
                    'can_deliver' => $service->status === 'in_progress',
                    'can_chat' => true,
                    'badge_text' => $service->ui_metadata['badge_text'] ?? null,
                ]
            ];
        }));
    }

    /**
     * Viajes donde el usuario es conductor
     */
    private function getRidesAsDriver(int $userId)
    {
        return collect(RideRequest::with(['passengers'])
            ->where('driver_id', $userId)
            ->whereIn('status', ['available', 'full', 'in_progress'])
            ->orderBy('departure_time', 'asc')
            ->get()
            ->map(function (RideRequest $ride) {
                $passengerCount = $ride->passengers()
                    ->wherePivotIn('status', ['confirmed', 'picked_up'])
                    ->count();
                
                $totalReserved = $ride->passengers()
                    ->wherePivotIn('status', ['confirmed', 'picked_up'])
                    ->sum('seats_reserved');

                return [
                    'id' =>  $ride->id,
                    'type' => 'trip',
                    'role' => 'driver',
                    'status' => $ride->status === 'in_progress' ? 'active' : 'pending',
                    'title' => $ride->getPublicationTitle(),
                    'description' => $ride->notes ?? "Viaje con {$ride->available_seats} asientos disponibles",
                    'price' => (float) $ride->price_per_seat,
                    'origin' => $ride->origin_address,
                    'destination' => $ride->destination_address,
                    'origin_city' => $ride->originCity?->name,
                    'destination_city' => $ride->destinationCity?->name,
                    'departure_time' => $ride->departure_time?->toISOString(),
                    'passengers_count' => (int) $totalReserved,
                    'available_seats' => (int) $ride->available_seats,
                    'total_seats' => (int) ($ride->total_seats ?? ($totalReserved + $ride->available_seats)),
                    'created_at' => $ride->created_at->toISOString(),

                    'passengers' => $ride->passengers()
                        ->wherePivotIn('status', ['confirmed', 'picked_up'])
                        ->get()
                        ->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'name' => $p->name,
                                'avatar' => $p->avatar_url,
                                'seats' => (int) $p->pivot->seats_reserved,
                                'status' => $p->pivot->status,
                            ];
                        }),

                    'ui' => [
                        'status_label' => $this->getRideStatusLabel($ride->status, 'driver'),
                        'status_color' => $this->getRideStatusColor($ride->status),
                        'can_start' => in_array($ride->status, ['available', 'full']),
                        'can_complete' => $ride->status === 'in_progress',
                        'can_chat' => true,
                    ]
                ];
            }));
    }

    private function getRidesAsPassenger(int $userId)
    {
        return collect(RidePassenger::with(['rideRequest.driver', 'rideRequest'])
            ->where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'picked_up', 'dropped_off'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (RidePassenger $passenger) {
                $ride = $passenger->rideRequest;

                return [
                    'id' => $ride->id,
                    'type' => 'trip',
                    'role' => 'passenger',
                    'status' => $passenger->status === 'picked_up' ? 'active' : 
                               ($passenger->status === 'dropped_off' ? 'completed' : 'pending'),
                    'title' => $ride->getPublicationTitle(),
                    'description' => "Reserva de {$passenger->seats_reserved} asiento(s)",
                    'price' => (float) $passenger->price_per_seat,
                    'total_paid' => (float) ($passenger->price_paid ?? 
                                     ($passenger->price_per_seat * $passenger->seats_reserved)),
                    'origin' => $ride->origin_address,
                    'destination' => $ride->destination_address,
                    'origin_city' => $ride->originCity?->name,
                    'destination_city' => $ride->destinationCity?->name,
                    'departure_time' => $ride->departure_time?->toISOString(),
                    'seats_reserved' => (int) $passenger->seats_reserved,
                    'created_at' => $passenger->created_at->toISOString(),

                    // Info del conductor
                    'driver' => [
                        'id' => $ride->driver_id,
                        'name' => $ride->driver?->name,
                        'avatar' => $ride->driver?->avatar_url,
                        'phone' => $ride->driver?->phone,
                        'rating' => $ride->driver?->rating,
                        'vehicle' => $ride->driver?->vehicle_info,
                    ],

                    // Mi estado en el viaje
                    'my_status' => $passenger->status,
                    'pickup_location' => $passenger->pickup_location,
                    'dropoff_location' => $passenger->dropoff_location,

                    'ui' => [
                        'status_label' => $this->getPassengerStatusLabel($passenger->status),
                        'status_color' => $this->getPassengerStatusColor($passenger->status),
                        'can_rate' => $passenger->status === 'dropped_off' && !$passenger->driver_rating,
                        'can_chat' => in_array($passenger->status, ['confirmed', 'picked_up']),
                        'can_cancel' => in_array($passenger->status, ['pending', 'confirmed']),
                    ]
                ];
            }));
    }

    // ==========================================
    // HELPERS DE MAPEO DE ESTADO
    // ==========================================

    /**
     * Mapea el estado interno del servicio al estado simplificado del frontend
     */
    private function mapServiceStatus(string $serviceStatus): string
    {
        return match($serviceStatus) {
            'open' => 'pending',      // Oferta aceptada pero aún no inicia
            'in_progress' => 'active', // Trabajando en ello
            'delivered' => 'pending',  // Entregado, esperando aprobación
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'disputed' => 'pending',
            default => 'pending',
        };
    }

    private function getServiceStatusLabel(string $status): string
    {
        return match($status) {
            'open' => 'Esperando inicio',
            'in_progress' => 'En progreso',
            'delivered' => 'Entregado - Esperando aprobación',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'disputed' => 'En disputa',
            default => $status,
        };
    }

    private function getServiceStatusColor(string $status): string
    {
        return match($status) {
            'open' => 'blue',
            'in_progress' => 'yellow',
            'delivered' => 'orange',
            'completed' => 'green',
            'cancelled' => 'red',
            'disputed' => 'red',
            default => 'gray',
        };
    }

    private function getRideStatusLabel(string $status, string $role): string
    {
        return match($status) {
            'available' => 'Publicado - Esperando pasajeros',
            'full' => 'Completo - Listo para salir',
            'in_progress' => 'En curso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    private function getRideStatusColor(string $status): string
    {
        return match($status) {
            'available' => 'blue',
            'full' => 'purple',
            'in_progress' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    private function getPassengerStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'Pendiente de confirmación',
            'confirmed' => 'Confirmado',
            'picked_up' => 'Recogido',
            'dropped_off' => 'Llegó a destino',
            'cancelled' => 'Cancelado',
            'no_show' => 'No se presentó',
            default => $status,
        };
    }

    private function getPassengerStatusColor(string $status): string
    {
        return match($status) {
            'pending' => 'orange',
            'confirmed' => 'blue',
            'picked_up' => 'yellow',
            'dropped_off' => 'green',
            'cancelled' => 'red',
            'no_show' => 'gray',
            default => 'gray',
        };
    }
}

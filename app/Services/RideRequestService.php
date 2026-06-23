<?php

namespace App\Services;

use App\Events\PassengerJoined;
use App\Events\PassengerStatusChanged;
use App\Events\RideStatusChanged;
use App\Exceptions\CannotJoinOwnRideException;
use App\Exceptions\InsufficientSeatsException;
use App\Models\Review;
use App\Models\RideRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RideRequestService
{
    public function __construct(
        private ReviewService $reviewService // ← Inyectas el genérico
    ) {}

    public function getAvailableRides(array $filters): LengthAwarePaginator
    {
        $query = RideRequest::with([
            'driver:id,name,rating,completed_jobs',
            'driver.about:user_id,avatar',
            'originCity:id,name',
            'destinationCity:id,name',
        ])
            ->select([
                'id',
                'driver_id',
                'origin_address',
                'origin_city_id',
                'destination_address',
                'destination_city_id',
                'departure_time',
                'available_seats',
                'total_seats',
                'price_per_seat',
                'status',
            ])
            ->whereIn('status', ['available', 'full'])
            ->where('departure_time', '>', now());

        // Filtro por distancia (radio en km)
        if (!empty($filters['origin_lat']) && !empty($filters['origin_lng'])) {
            $radius = $filters['radius'] ?? 5;
            $query->whereRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(origin_lat)) * cos(radians(origin_lng) - radians(?)) + sin(radians(?)) * sin(radians(origin_lat)))) <= ?",
                [$filters['origin_lat'], $filters['origin_lng'], $filters['origin_lat'], $radius]
            );
        }

        // Filtro por fecha específica
        if (!empty($filters['date'])) {
            $query->whereDate('departure_time', $filters['date']);
        }

        return $query->orderBy('departure_time')->paginate(20);
    }


    public function createRide(User $user, array $data): RideRequest
    {
        // Mapeo: frontend envía "vehicle_brand", DB espera "vehicle_make"
        if (!empty($data['vehicle_brand'])) {
            $data['vehicle_make'] = $data['vehicle_brand'];
        }
        unset($data['vehicle_brand']);

        // Total = disponibles al crear (luego se decrementa al reservar)
        $data['total_seats'] = $data['available_seats'];

        // Status por defecto
        $data['status'] = 'available';

        return $user->rideRequests()->create($data);
    }

    public function joinRide(RideRequest $ride, User $user, array $data): void
    {
        if ($ride->isDriver($user)) {
            throw new CannotJoinOwnRideException('No puedes unirte a tu propio viaje');
        }

        if ($ride->isPassenger($user)) {
            throw new \InvalidArgumentException('Ya solicitaste unirte a este viaje');
        }

        if (!in_array($ride->status, ['available', 'full'])) {
            throw new \InvalidArgumentException('El viaje no acepta más pasajeros');
        }

        $seats = $data['seats'] ?? 1;

        if (!$ride->hasAvailableSeats($seats)) {
            throw new InsufficientSeatsException('No hay suficientes asientos');
        }

        $ride->passengers()->attach($user->id, [
            'seats_reserved' => $seats,
            'status' => 'pending',
            'price_per_seat' => $ride->price_per_seat,
            'pickup_location' => $data['pickup_location'] ?? null,
            'dropoff_location' => $data['dropoff_location'] ?? null,
            'special_requests' => $data['special_requests'] ?? null,
        ]);

        $ride->increment('passenger_requests_count');

        broadcast(new PassengerJoined($ride, $user))->toOthers();
    }

    public function confirmPassenger(RideRequest $ride, int $passengerId, User $driver): void
    {
        if (!$ride->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede confirmar');
        }

        $ride->passengers()->updateExistingPivot($passengerId, [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        // Decrementar asientos disponibles
        $pivot = $ride->passengers()->where('user_id', $passengerId)->first()->pivot;
        $ride->decrement('available_seats', $pivot->seats_reserved);

        if ($ride->available_seats === 0) {
            $ride->update(['status' => 'full']);
        }

        broadcast(new PassengerStatusChanged($ride, $passengerId, 'confirmed'));
    }

    public function startRide(RideRequest $ride, User $driver): void
    {
        if (!$ride->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede iniciar');
        }

        if (!in_array($ride->status, ['available', 'full'])) {
            throw new \InvalidArgumentException('El viaje ya fue iniciado o cancelado');
        }

        $ride->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        broadcast(new RideStatusChanged($ride, 'in_progress'));
    }

    public function markPickedUp(RideRequest $ride, int $passengerId, User $driver): void
    {
        if (!$ride->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede marcar recogida');
        }

        $ride->passengers()->updateExistingPivot($passengerId, [
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);
        broadcast(new PassengerStatusChanged($ride, $passengerId, 'picked_up'));
    }

    public function markDroppedOff(RideRequest $ride, int $passengerId, User $driver): void
    {
        if (!$ride->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede marcar llegada');
        }

        $ride->passengers()->updateExistingPivot($passengerId, [
            'status' => 'dropped_off',
            'dropped_off_at' => now(),
        ]);
        broadcast(new PassengerStatusChanged($ride, $passengerId, 'dropped_off'));
    }

    public function completeRide(RideRequest $ride, User $driver): void
    {
        if (!$ride->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede completar');
        }

        if ($ride->status !== 'in_progress') {
            throw new \InvalidArgumentException('El viaje no está en curso');
        }

        // Marcar todos los pasajeros confirmados/recogidos como dropped_off si no lo están
        $ride->passengers()
            ->wherePivotIn('status', ['confirmed', 'picked_up'])
            ->updateExistingPivot(
                $ride->passengers()->wherePivotIn('status', ['confirmed', 'picked_up'])->pluck('users.id'),
                ['status' => 'dropped_off', 'dropped_off_at' => now()]
            );

        $ride->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        broadcast(new RideStatusChanged($ride, 'completed'));
    }

    public function cancelRide(RideRequest $ride, User $user, ?string $reason = null): void
    {
        $isDriver = $ride->isDriver($user);

        if ($isDriver) {
            // Conductor cancela TODO el viaje
            if (!in_array($ride->status, ['available', 'full', 'in_progress'])) {
                throw new \InvalidArgumentException('No se puede cancelar este viaje');
            }

            // Devolver asientos a todos los pasajeros confirmados/pending
            foreach ($ride->passengers as $passenger) {
                if (in_array($passenger->pivot->status, ['pending', 'confirmed', 'picked_up'])) {
                    $ride->passengers()->updateExistingPivot($passenger->id, [
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancellation_reason' => $reason ?? 'Conductor canceló el viaje',
                    ]);
                    broadcast(new PassengerStatusChanged($ride, $passenger->id, 'cancelled', $reason));
                }
            }

            $ride->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            broadcast(new RideStatusChanged($ride, 'cancelled', $reason));

            return;
        }

        // Pasajero cancela SU reserva
        $pivot = $ride->passengers()->where('user_id', $user->id)->first()?->pivot;

        if (!$pivot || !in_array($pivot->status, ['pending', 'confirmed'])) {
            throw new \InvalidArgumentException('No tienes una reserva activa');
        }

        $seats = $pivot->seats_reserved;

        $ride->passengers()->updateExistingPivot($user->id, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Si estaba confirmed, devolver asientos
        if ($pivot->status === 'confirmed') {
            $ride->increment('available_seats', $seats);

            if ($ride->status === 'full') {
                $ride->update(['status' => 'available']);
            }
        }
        broadcast(new PassengerStatusChanged($ride, $user->id, 'cancelled', $reason));
    }

    public function rateRide(RideRequest $ride, User $user, array $data): void
    {
        $targetId = $data['target_user_id'];

        // Validar que el target sea parte del viaje
        $isTargetDriver = $ride->driver_id === $targetId;
        $isTargetPassenger = $ride->passengers()->where('user_id', $targetId)->exists();

        if (!$isTargetDriver && !$isTargetPassenger) {
            throw new \InvalidArgumentException('Usuario no participó en este viaje');
        }

        // Validar que el calificador sea parte del viaje
        if ($ride->driver_id !== $user->id && !$ride->isPassenger($user)) {
            throw new \InvalidArgumentException('No participaste en este viaje');
        }

        // Evitar doble calificación
        $alreadyRated = Review::where('reviewer_id', $user->id)
            ->where('reviewed_user_id', $targetId)
            ->where('reviewable_type', get_class($ride))
            ->where('reviewable_id', $ride->id)
            ->exists();

        if ($alreadyRated) {
            throw new \InvalidArgumentException('Ya calificaste a este usuario');
        }

        // Validar rating
        if ($data['rating'] < 1 || $data['rating'] > 5) {
            throw new \InvalidArgumentException('La calificación debe estar entre 1 y 5');
        }

        // Crear review (asumiendo modelo Review)
        $this->reviewService->createReview($user, [
            'reviewed_user_id' => $targetId,
            'reviewable_type'  => RideRequest::class,
            'reviewable_id'    => $ride->id,
            'rating'           => $data['rating'],
            'comment'          => $data['comment'] ?? null,
        ]);

        // Actualizar rating del calificado
        User::find($targetId)->updateRating();
    }

    public function getUserRides(User $user): array
    {
        return [
            'as_driver' => $user->rideRequests()->latest()->get(),
            'as_passenger' => $user->ridePassengers()->latest()->get(),
        ];
    }

    public function findRide(int $id, $with = []): RideRequest
    {
        $query = RideRequest::with($with ?: ['driver', 'passengers']);
        return $query->findOrFail($id);
    }

    public function getStats(User $user, string $period, array $dateRange, array $previousRange): array
    {
        [$from, $to]         = $dateRange;
        [$prevFrom, $prevTo] = $previousRange;
 
        return [
            'period'       => $period,
            'kpis'         => $this->buildKpis($user, $from, $to, $prevFrom, $prevTo),
            'weekly_chart' => $this->buildWeeklyChart($user, $from, $to),
            'heatmap'      => $this->buildHeatmap($user),
            'insights'     => $this->buildInsights($user, $from, $to),
        ];
    }
 
    // ─── KPIs ─────────────────────────────────────────────────────────────────
 
    private function buildKpis(
        User $user,
        Carbon $from, Carbon $to,
        Carbon $prevFrom, Carbon $prevTo
    ): array {
        $current  = $this->fetchKpiData($user, $from, $to);
        $previous = $this->fetchKpiData($user, $prevFrom, $prevTo);
 
        return [
            'earnings' => [
                'current' => $current['earnings'],
                'change'  => $this->percentChange($previous['earnings'], $current['earnings']),
            ],
            'rides' => [
                'current' => $current['rides'],
                'change'  => $this->percentChange($previous['rides'], $current['rides']),
            ],
            'passengers' => [
                'current' => $current['passengers'],
                'change'  => $this->percentChange($previous['passengers'], $current['passengers']),
            ],
            'rating' => [
                'current' => $current['rating'],
                'change'  => $this->percentChange($previous['rating'], $current['rating']),
            ],
        ];
    }
 
    /**
     * Un solo query JOIN para obtener ganancias, viajes y pasajeros del período.
     */
    private function fetchKpiData(User $user, Carbon $from, Carbon $to): array
    {
        $row = DB::table('publications as p')
            ->join('ride_requests as r', function ($join) {
                $join->on('r.id', '=', 'p.publishable_id')
                     ->where('p.publishable_type', RideRequest::class);
            })
            ->where('p.user_id', $user->id)
            ->where('p.category', 'ride')
            ->where('p.status', 'completed')
            ->whereBetween('r.completed_at', [$from, $to])
            ->selectRaw('
                COUNT(r.id)                                                          AS rides,
                COALESCE(SUM(r.total_seats - r.available_seats), 0)                 AS passengers,
                COALESCE(SUM(r.price_per_seat * (r.total_seats - r.available_seats)), 0) AS earnings
            ')
            ->first();
 
        // Rating promedio de reseñas recibidas como conductor en el período
        $rating = DB::table('reviews')
            ->where('reviewed_user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('rating')
            ->avg('rating') ?? 0.0;
 
        return [
            'rides'      => (int)   ($row->rides      ?? 0),
            'passengers' => (int)   ($row->passengers ?? 0),
            'earnings'   => (float) ($row->earnings   ?? 0),
            'rating'     => round((float) $rating, 2),
        ];
    }
 
    // ─── Gráfica semanal ──────────────────────────────────────────────────────
 
    /**
     * Agrupa los viajes completados por día-de-semana dentro del rango.
     * Normaliza los valores a 0–100 para la barra de altura.
     */
    private function buildWeeklyChart(User $user, Carbon $from, Carbon $to): array
    {
        $days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
 
        // Ganancias por día de la semana (1=Lunes ... 7=Domingo en MySQL DAYOFWEEK ajustado)
        $rows = DB::table('publications as p')
            ->join('ride_requests as r', function ($join) {
                $join->on('r.id', '=', 'p.publishable_id')
                     ->where('p.publishable_type', RideRequest::class);
            })
            ->where('p.user_id', $user->id)
            ->where('p.category', 'ride')
            ->where('p.status', 'completed')
            ->whereBetween('r.completed_at', [$from, $to])
            ->selectRaw('
                -- DAYOFWEEK: 1=Dom...7=Sáb → ajustamos a Lun=1...Dom=7
                MOD(DAYOFWEEK(r.completed_at) + 5, 7) + 1  AS dow,
                COALESCE(SUM(r.price_per_seat * (r.total_seats - r.available_seats)), 0) AS earnings
            ')
            ->groupBy('dow')
            ->pluck('earnings', 'dow'); // keyed by 1–7
 
        // Construir los 7 días con su valor real
        $rawValues = collect(range(1, 7))->map(fn($i) => (float) ($rows[$i] ?? 0));
 
        $max = $rawValues->max() ?: 1; // evitar división por 0
 
        return $rawValues->map(fn($raw, $idx) => [
            'label' => $days[$idx],
            'value' => (int) round(($raw / $max) * 100), // 0–100
            'raw'   => $raw,
        ])->values()->all();
    }
 
    // ─── Mapa de calor ────────────────────────────────────────────────────────
 
    /**
     * Últimos 35 días (5 semanas × 7 días) con intensidad 0–4.
     */
    private function buildHeatmap(User $user): array
    {
        $from = now()->subDays(34)->startOfDay();
        $to   = now()->endOfDay();
 
        // Viajes por día
        $rows = DB::table('publications as p')
            ->join('ride_requests as r', function ($join) {
                $join->on('r.id', '=', 'p.publishable_id')
                     ->where('p.publishable_type', RideRequest::class);
            })
            ->where('p.user_id', $user->id)
            ->where('p.category', 'ride')
            ->where('p.status', 'completed')
            ->whereBetween('r.completed_at', [$from, $to])
            ->selectRaw('DATE(r.completed_at) AS day, COUNT(*) AS rides')
            ->groupBy('day')
            ->pluck('rides', 'day'); // keyed by 'Y-m-d'
 
        $maxRides = $rows->max() ?: 1;
 
        return collect(range(0, 34))->map(function ($offset) use ($from, $rows, $maxRides) {
            $date  = $from->copy()->addDays($offset);
            $key   = $date->format('Y-m-d');
            $rides = (int) ($rows[$key] ?? 0);
 
            // Intensidad 0–4 proporcional al día más activo
            $intensity = $rides === 0
                ? 0
                : (int) ceil(($rides / $maxRides) * 4);
 
            return [
                'day'       => $offset + 1,
                'date'      => $key,
                'rides'     => $rides,
                'intensity' => min($intensity, 4),
            ];
        })->values()->all();
    }
 
    // ─── Insights ─────────────────────────────────────────────────────────────
 
    private function buildInsights(User $user, Carbon $from, Carbon $to): array
    {
        $insights = [];
 
        // 1. Día más productivo del período
        $bestDay = DB::table('publications as p')
            ->join('ride_requests as r', function ($join) {
                $join->on('r.id', '=', 'p.publishable_id')
                     ->where('p.publishable_type', RideRequest::class);
            })
            ->where('p.user_id', $user->id)
            ->where('p.category', 'ride')
            ->where('p.status', 'completed')
            ->whereBetween('r.completed_at', [$from, $to])
            ->selectRaw('DAYNAME(r.completed_at) AS day_name, COUNT(*) AS rides')
            ->groupBy('day_name')
            ->orderByDesc('rides')
            ->first();
 
        if ($bestDay) {
            $dayEs = $this->translateDayName($bestDay->day_name);
            $insights[] = [
                'icon'        => 'trophy-outline',
                'color'       => '#f59e0b',
                'bg'          => 'rgba(245,158,11,0.1)',
                'title'       => 'Día más productivo',
                'description' => "{$dayEs} con {$bestDay->rides} viaje" . ($bestDay->rides > 1 ? 's' : '') . ' completado' . ($bestDay->rides > 1 ? 's' : ''),
                'trend'       => 'up',
                'percent'     => 28,
            ];
        }
 
        // 2. Horario pico (hora con más viajes)
        $peakHour = DB::table('publications as p')
            ->join('ride_requests as r', function ($join) {
                $join->on('r.id', '=', 'p.publishable_id')
                     ->where('p.publishable_type', RideRequest::class);
            })
            ->where('p.user_id', $user->id)
            ->where('p.category', 'ride')
            ->where('p.status', 'completed')
            ->whereBetween('r.completed_at', [$from, $to])
            ->selectRaw('HOUR(r.departure_time) AS hour, COUNT(*) AS rides')
            ->groupBy('hour')
            ->orderByDesc('rides')
            ->first();
 
        if ($peakHour) {
            $hourLabel = sprintf('%02d:00 - %02d:00 %s',
                $peakHour->hour,
                ($peakHour->hour + 2) % 24,
                $peakHour->hour < 12 ? 'AM' : 'PM'
            );
            $insights[] = [
                'icon'        => 'time-outline',
                'color'       => '#3b82f6',
                'bg'          => 'rgba(59,130,246,0.1)',
                'title'       => 'Horario pico',
                'description' => "{$hourLabel}, mayor demanda",
                'trend'       => 'up',
                'percent'     => 42,
            ];
        }
 
        // 3. Ruta más frecuente
        $topRoute = DB::table('publications as p')
            ->join('ride_requests as r', function ($join) {
                $join->on('r.id', '=', 'p.publishable_id')
                     ->where('p.publishable_type', RideRequest::class);
            })
            ->join('cities as oc', 'oc.id', '=', 'r.origin_city_id')
            ->join('cities as dc', 'dc.id', '=', 'r.destination_city_id')
            ->where('p.user_id', $user->id)
            ->where('p.category', 'ride')
            ->where('p.status', 'completed')
            ->whereBetween('r.completed_at', [$from, $to])
            ->selectRaw('oc.name AS origin, dc.name AS destination, COUNT(*) AS trips')
            ->groupBy('oc.name', 'dc.name')
            ->orderByDesc('trips')
            ->first();
 
        if ($topRoute) {
            $insights[] = [
                'icon'        => 'pulse-outline',
                'color'       => '#ef4444',
                'bg'          => 'rgba(239,68,68,0.1)',
                'title'       => 'Ruta estrella',
                'description' => "{$topRoute->origin} → {$topRoute->destination}, {$topRoute->trips} repeticiones",
                'trend'       => 'neutral',
                'percent'     => 0,
            ];
        }
 
        return $insights;
    }
 
    // ─── Utilidades ───────────────────────────────────────────────────────────
 
    private function percentChange(float $old, float $new): float
    {
        if ($old == 0) {
            return $new > 0 ? 100.0 : 0.0;
        }
        return round((($new - $old) / $old) * 100, 1);
    }
 
    private function translateDayName(string $englishDay): string
    {
        return match (strtolower($englishDay)) {
            'monday'    => 'Lunes',
            'tuesday'   => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday'  => 'Jueves',
            'friday'    => 'Viernes',
            'saturday'  => 'Sábado',
            'sunday'    => 'Domingo',
            default     => $englishDay,
        };
    }
}

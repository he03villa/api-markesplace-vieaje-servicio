<?php

namespace App\Models;

use App\Traits\HasPublication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RideRequest extends Model
{
    use HasFactory, HasPublication;

    protected $fillable = [
        'driver_id',
        // Origen
        'origin_address',
        'origin_lat',
        'origin_lng',
        'origin_country_id',
        'origin_state_id',
        'origin_city_id',
        'origin_city',
        'origin_state',
        // Destino
        'destination_address',
        'destination_lat',
        'destination_lng',
        'destination_country_id',
        'destination_state_id',
        'destination_city_id',
        'destination_city',
        'destination_state',
        // Viaje
        'departure_time',
        'available_seats',
        'total_seats',
        'price_per_seat',
        'status',
        'started_at',
        'completed_at',
        'notes',
        // Vehículo
        'vehicle_make',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'price_per_seat' => 'decimal:2',
        'available_seats' => 'integer',
        'total_seats' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'origin_full_location',
        'destination_full_location',
        'origin_country_flag',
        'destination_country_flag',
        'estimated_distance',
        'is_full',
    ];

    // RELACIONES
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function passengers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ride_passengers')
            ->withPivot([
                'seats_reserved',
                'status',
                'price_paid',
                'price_per_seat',
                'pickup_location',
                'dropoff_location',
                'driver_rating',
                'driver_comment',
                'passenger_rating',
                'passenger_comment',
                'payment_status',
                'payment_method',
                'payment_id',
                'paid_at',
                'confirmed_at',
                'cancelled_at',
                'completed_at',
                'picked_up_at',      // ← NUEVO
                'dropped_off_at',    // ← NUEVO
                'special_requests',
                'no_show',
                'cancellation_reason',
                'confirmed_at',
            ])
            ->withTimestamps();
    }

    public function confirmedPassengers()
    {
        return $this->passengers()->wherePivot('status', 'confirmed');
    }

    // Relaciones geográficas...
    public function originCountry()
    {
        return $this->belongsTo(\App\Models\Country::class, 'origin_country_id');
    }

    public function originState()
    {
        return $this->belongsTo(\App\Models\State::class, 'origin_state_id');
    }

    public function originCity()
    {
        return $this->belongsTo(\App\Models\City::class, 'origin_city_id');
    }

    public function destinationCountry()
    {
        return $this->belongsTo(\App\Models\Country::class, 'destination_country_id');
    }

    public function destinationState()
    {
        return $this->belongsTo(\App\Models\State::class, 'destination_state_id');
    }

    public function destinationCity()
    {
        return $this->belongsTo(\App\Models\City::class, 'destination_city_id');
    }

    // MÉTODOS DE ESTADO Y LÓGICA
    public function hasAvailableSeats(int $requestedSeats = 1): bool
    {
        return $this->available_seats >= $requestedSeats;
    }

    public function isDriver(User $user): bool
    {
        return $this->driver_id === $user->id;
    }

    public function isPassenger(User $user): bool
    {
        return $this->passengers()->where('user_id', $user->id)->exists();
    }

    public function getIsFullAttribute(): bool
    {
        return $this->available_seats === 0;
    }

    /**
     * NUEVO: Valida transiciones de estado para rides
     */
    public function validateStatusTransition(string $current, string $new, ?User $user = null): bool
    {
        if (!$user) {
            $allowed = [
                'available' => ['full', 'in_progress', 'cancelled'],
                'full' => ['in_progress', 'cancelled'],
                'in_progress' => ['completed', 'cancelled'],
            ];
            return isset($allowed[$current]) && in_array($new, $allowed[$current]);
        }

        $isDriver = $this->isDriver($user);

        return match ("$current->$new") {
            'available->full' => false, // Automático al llenarse
            'available->in_progress' => $isDriver, // Conductor inicia viaje
            'available->cancelled' => $isDriver,

            'full->in_progress' => $isDriver, // Conductor inicia aunque esté lleno
            'full->cancelled' => $isDriver,

            'in_progress->completed' => $isDriver, // Conductor marca como completado
            'in_progress->cancelled' => $isDriver, // O condiciones especiales

            default => false,
        };
    }

    /**
     * NUEVO: Unirse al viaje (mejorado desde el Service)
     */
    public function join(User $user, int $seats = 1, array $extraData = []): void
    {
        if ($this->isDriver($user)) {
            throw new \InvalidArgumentException('No puedes unirte a tu propio viaje');
        }

        if ($this->isPassenger($user)) {
            throw new \InvalidArgumentException('Ya eres pasajero de este viaje');
        }

        if (!$this->hasAvailableSeats($seats)) {
            throw new \InvalidArgumentException('No hay suficientes asientos');
        }

        if (!in_array($this->status, ['available', 'full'])) {
            throw new \InvalidArgumentException('El viaje no está disponible');
        }

        $this->passengers()->attach($user->id, array_merge([
            'seats_reserved' => $seats,
            'status' => 'confirmed',
            'price_per_seat' => $this->price_per_seat,
            'confirmed_at' => now(),
        ], $extraData));

        $this->decrement('available_seats', $seats);

        // Si se llenó, cambiar a full
        if ($this->available_seats === 0 && $this->status === 'available') {
            $this->update(['status' => 'full']);
        }
    }

    /**
     * NUEVO: Conductor inicia el viaje
     */
    public function start(User $driver): void
    {
        if (!$this->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede iniciar');
        }

        $this->transitionTo('in_progress', $driver);
        $this->update(['started_at' => now()]);

        // Marcar pasajeros como "picked_up" o similar según tu lógica
    }

    /**
     * NUEVO: Conductor completa el viaje
     */
    public function complete(User $driver): void
    {
        if (!$this->isDriver($driver)) {
            throw new \InvalidArgumentException('Solo el conductor puede completar');
        }

        $this->transitionTo('completed', $driver);
        $this->update(['completed_at' => now()]);

        // Actualizar pasajeros a dropped_off
        $this->passengers()->updateExistingPivot(
            $this->passengers()->pluck('users.id'),
            ['status' => 'dropped_off', 'dropped_off_at' => now()]
        );
    }

    /**
     * NUEVO: Cancelar reserva de pasajero
     */
    public function cancelPassenger(User $passenger, ?string $reason = null): void
    {
        $pivot = $this->passengers()->where('user_id', $passenger->id)->first()?->pivot;

        if (!$pivot || $pivot->status === 'cancelled') {
            throw new \InvalidArgumentException('No hay reserva activa');
        }

        $seats = $pivot->seats_reserved;

        $this->passengers()->updateExistingPivot($passenger->id, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->increment('available_seats', $seats);

        // Si estaba full, volver a available
        if ($this->status === 'full') {
            $this->update(['status' => 'available']);
        }
    }

    // ACCESSORS Y SCOPES...
    public function getOriginFullLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->originCity?->name,
            $this->originState?->name,
            $this->originCountry?->name,
        ]);
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    public function getDestinationFullLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->destinationCity?->name,
            $this->destinationState?->name,
            $this->destinationCountry?->name,
        ]);
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    public function getOriginCountryFlagAttribute(): ?string
    {
        return $this->originCountry?->emoji;
    }

    public function getDestinationCountryFlagAttribute(): ?string
    {
        return $this->destinationCountry?->emoji;
    }

    public function getEstimatedDistanceAttribute(): ?float
    {
        if (!$this->origin_lat || !$this->origin_lng || !$this->destination_lat || !$this->destination_lng) {
            return null;
        }
        return $this->calculateDistance(
            $this->origin_lat,
            $this->origin_lng,
            $this->destination_lat,
            $this->destination_lng
        );
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    // SCOPES...
    public function scopeFromCity($query, int $cityId)
    {
        return $query->where('origin_city_id', $cityId);
    }

    public function scopeToCity($query, int $cityId)
    {
        return $query->where('destination_city_id', $cityId);
    }

    public function scopeRoute($query, int $originCityId, int $destinationCityId)
    {
        return $query->where('origin_city_id', $originCityId)
            ->where('destination_city_id', $destinationCityId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('departure_time', '>', now())
            ->whereIn('status', ['available', 'full']);
    }

    // HasPublication implementation
    protected function getPublicationCategory(): string
    {
        return 'ride';
    }

    protected function getPublicationSubCategory(): ?string
    {
        return 'carpool';
    }

    function getPublicationTitle(): string
    {
        $origin = $this->originCity?->name ?? $this->origin_address;
        $destination = $this->destinationCity?->name ?? $this->destination_address;
        return "{$origin} → {$destination}";
    }

    protected function getPublicationDescription(): ?string
    {
        return $this->notes ?? "Viaje con {$this->available_seats} asientos disponibles";
    }

    protected function getOffersCount(): int
    {
        return $this->confirmedPassengers()->count();
    }

    protected function getUiMetadata(): array
    {
        $reservedSeats = $this->confirmedPassengers()->sum('seats_reserved') ?? 0;
        $available = $this->available_seats;
        $total = $this->total_seats ?? ($reservedSeats + $available);

        // Badge según estado
        $badgeConfig = match ($this->status) {
            'available' => [
                'text' => "{$available} asiento" . ($available !== 1 ? 's' : '') . " disponible" . ($available !== 1 ? 's' : ''),
                'color' => 'blue'
            ],
            'full' => ['text' => 'Completo - Esperando salida', 'color' => 'orange'],
            'in_progress' => ['text' => 'En curso', 'color' => 'yellow'],
            'completed' => ['text' => 'Completado', 'color' => 'green'],
            'cancelled' => ['text' => 'Cancelado', 'color' => 'red'],
            default => ['text' => $this->status, 'color' => 'gray'],
        };

        return [
            'badge_text' => $badgeConfig['text'],
            'badge_color' => $badgeConfig['color'],
            'subtitle' => $this->departure_time->format('d M, h:i A'),
            'route' => $this->getPublicationTitle(),
            'origin' => $this->origin_full_location,
            'destination' => $this->destination_full_location,
            'price' => "$" . number_format($this->price_per_seat, 0) . "/asiento",
            'departure_time' => $this->departure_time->format('d M, h:i A'),
            'seats' => [
                'available' => $available,
                'total' => $total,
                'reserved' => $reservedSeats,
            ],
            'distance' => $this->estimated_distance ? $this->estimated_distance . ' km' : null,
            'status' => $this->status,
            'type_icon' => 'car',
            'type_color' => 'emerald',
        ];
    }

    public function hasUserRated(User $user, int $targetUserId): bool
    {
        return Review::where('reviewer_id', $user->id)
            ->where('reviewed_user_id', $targetUserId)
            ->where('reviewable_type', self::class)
            ->where('reviewable_id', $this->id)
            ->exists();
    }

    public function getMyReviewFor(User $user, int $targetUserId): ?Review
    {
        return Review::where('reviewer_id', $user->id)
            ->where('reviewed_user_id', $targetUserId)
            ->where('reviewable_type', self::class)
            ->where('reviewable_id', $this->id)
            ->first();
    }
}

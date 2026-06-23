<?php

namespace App\Models;

use App\Traits\HasPublication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory, HasPublication;

    protected $fillable = [
        'user_id',
        'worker_id',
        'title',
        'description',
        'category',
        'address',
        'latitude',
        'longitude',
        'budget_min',
        'budget_max',
        'deadline',
        'status',
        'images',
        'country_id',
        'state_id',
        'city_id',
        'delivered_at',
        'completed_at',
    ];

    protected $casts = [
        'images' => 'array',
        'deadline' => 'datetime',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'delivered_at' => 'datetime', // NUEVO
        'completed_at' => 'datetime', // NUEVO
    ];

    protected $appends = ['full_location', 'country_flag', 'country_code', 'first_review'];

    // RELACIONES
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function acceptedOffer()
    {
        return $this->hasOne(Offer::class)->where('status', 'accepted');
    }

    public function serviceRequestsDelivered()
    {
        return $this->hasOne(ServiceRequestDelivery::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class);
    }

    public function state()
    {
        return $this->belongsTo(\App\Models\State::class);
    }

    public function city()
    {
        return $this->belongsTo(\App\Models\City::class);
    }

    // PERMISOS Y ESTADOS
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function canBeEditedBy(User $user): bool
    {
        // Solo editable si está open y es el dueño
        return $this->user_id === $user->id && $this->status === 'open';
    }

    /**
     * NUEVO: Valida transiciones de estado específicas de servicios
     */
    public function validateStatusTransition(string $current, string $new, ?User $user = null): bool
    {
        // Si no hay usuario, solo validación de estados posibles
        if (!$user) {
            $allowed = [
                'open' => ['in_progress', 'cancelled', 'expired'],
                'in_progress' => ['delivered', 'cancelled'],
                'delivered' => ['completed', 'disputed'],
                'disputed' => ['completed', 'cancelled'],
            ];
            return isset($allowed[$current]) && in_array($new, $allowed[$current]);
        }

        // Con usuario: validar permisos
        $isOwner = $this->user_id === $user->id;
        $isWorker = $this->worker_id === $user->id;

        return match ("$current->$new") {
            'open->in_progress' => $isOwner, // Solo dueño acepta oferta
            'open->cancelled' => $isOwner,
            'open->expired' => false, // Solo sistema

            'in_progress->delivered' => $isWorker, // Solo worker entrega
            'in_progress->cancelled' => $isOwner || $isWorker, // Ambos pueden cancelar

            'delivered->completed' => $isOwner, // Solo dueño aprueba
            'delivered->disputed' => $isOwner, // Solo dueño disputa

            'disputed->completed' => $user->isAdmin(), // Solo admin resuelve
            'disputed->cancelled' => $user->isAdmin(),

            default => false,
        };
    }

    /**
     * NUEVO: Aceptar una oferta y asignar worker
     */
    public function acceptOffer(Offer $offer): void
    {
        if (!$this->isOpen()) {
            throw new \InvalidArgumentException('La solicitud no está abierta');
        }

        if ($offer->service_request_id !== $this->id) {
            throw new \InvalidArgumentException('La oferta no pertenece a esta solicitud');
        }

        // Rechazar otras ofertas
        $this->offers()->where('id', '!=', $offer->id)->update(['status' => 'rejected']);

        // Aceptar esta oferta
        $offer->update(['status' => 'accepted']);

        $this->update([
            'worker_id' => $offer->user_id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * NUEVO: Worker marca como entregado
     */
    public function markAsDelivered(User $worker): void
    {
        $this->transitionTo('delivered', $worker);
        $this->update(['delivered_at' => now()]);

        // Notificar al dueño
        // event(new ServiceDelivered($this));
    }

    /**
     * NUEVO: Dueño aprueba el trabajo
     */
    public function markAsCompleted(User $owner): void
    {
        if ($this->user_id !== $owner->id) {
            throw new \InvalidArgumentException('No autorizado');
        }

        $this->transitionTo('completed', $owner);
        $this->update(['completed_at' => now()]);

        // Liberar pago al worker si hay escrow
        // $this->releasePayment();
    }

    /**
     * NUEVO: Dueño abre disputa
     */
    public function openDispute(User $owner, string $reason): void
    {
        if ($this->user_id !== $owner->id) {
            throw new \InvalidArgumentException('No autorizado');
        }

        $this->transitionTo('disputed', $owner);

        // Crear registro de disputa
        // Dispute::create(['service_request_id' => $this->id, 'reason' => $reason]);
    }

    // ACCESSORS
    public function getFullLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->city?->name,
            $this->state?->name,
            $this->country?->name,
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    public function getCountryFlagAttribute(): ?string
    {
        return $this->country?->emoji;
    }

    public function getCountryCodeAttribute(): ?string
    {
        return $this->country?->iso2;
    }

    // HasPublication implementation
    protected function getPublicationCategory(): string
    {
        return 'service';
    }

    protected function getPublicationSubCategory(): ?string
    {
        return $this->category;
    }

    protected function getPublicationTitle(): string
    {
        return $this->title;
    }

    protected function getPublicationDescription(): ?string
    {
        return $this->description;
    }

    public function getFirstReviewAttribute(): ?Review
    {
        return $this->reviews()->first();
    }

    protected function getOffersCount(): int
    {
        return $this->offers()->count();
    }

    protected function getUiMetadata(): array
    {
        $offerCount = $this->offers()->count();

        // Texto según estado
        $statusText = match ($this->status) {
            'open' => $offerCount > 0 ? "{$offerCount} oferta" . ($offerCount > 1 ? 's' : '') : 'Sin ofertas',
            'in_progress' => 'En progreso',
            'delivered' => 'Entregado - Esperando aprobación',
            'completed' => 'Completado',
            'disputed' => 'En disputa',
            'cancelled' => 'Cancelado',
            'expired' => 'Expirado',
            default => $this->status,
        };

        $statusColor = match ($this->status) {
            'open' => $offerCount > 0 ? 'green' : 'gray',
            'in_progress' => 'yellow',
            'delivered' => 'orange',
            'completed' => 'green',
            'disputed' => 'red',
            'cancelled', 'expired' => 'gray',
            default => 'gray',
        };

        return [
            'badge_text' => $statusText,
            'badge_color' => $statusColor,
            'subtitle' => "Publicado {$this->created_at->diffForHumans()}",
            'category_label' => $this->category,
            'location' => $this->full_location,
            'has_images' => !empty($this->images),
            'budget_range' => $this->getBudgetRangeText(),
            'deadline' => $this->deadline?->format('d M Y'),
            'status' => $this->status,
            'worker_assigned' => $this->worker_id !== null,
            'type_icon' => 'document-text',
            'type_color' => 'indigo',
        ];
    }

    private function getBudgetRangeText(): ?string
    {
        if ($this->budget_min && $this->budget_max) {
            return "$" . number_format($this->budget_min, 0) . " - $" . number_format($this->budget_max, 0);
        }
        if ($this->budget_min) {
            return "Desde $" . number_format($this->budget_min, 0);
        }
        if ($this->budget_max) {
            return "Hasta $" . number_format($this->budget_max, 0);
        }
        return null;
    }
}

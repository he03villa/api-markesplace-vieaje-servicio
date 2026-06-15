<?php

namespace App\Traits;

use App\Models\Publication;
use Illuminate\Support\Facades\Log;

trait HasPublication
{
    protected static function bootHasPublication()
    {
        static::created(function ($model) {
            $model->syncPublication();
        });

        static::updated(function ($model) {
            if ($model->isDirty('status')) {
                $model->syncPublication();
            }
        });

        static::deleted(function ($model) {
            $model->publication?->delete();
        });
    }

    public function publication()
    {
        return $this->morphOne(Publication::class, 'publishable');
    }

    public function syncPublication(): void
    {
        $data = [
            'user_id' => $this->user_id ?? $this->driver_id,
            'title' => $this->getPublicationTitle(),
            'description' => $this->getPublicationDescription(),
            'category' => $this->getPublicationCategory(),
            'sub_category' => $this->getPublicationSubCategory(),
            'status' => static::mapStatus($this->status ?? 'active'),
            'offers_count' => $this->getOffersCount(),
            'ui_metadata' => $this->getUiMetadata(),
            'published_at' => $this->created_at,
        ];

        if ($this->publication) {
            $this->publication->update($data);
        } else {
            $this->publication()->create($data);
        }
    }

    // MÉTODOS ABSTRACTOS (debes implementar en cada modelo)
    abstract protected function getPublicationCategory(): string;
    abstract protected function getPublicationSubCategory(): ?string;
    abstract protected function getPublicationTitle(): string;
    abstract protected function getPublicationDescription(): ?string;
    abstract protected function getOffersCount(): int;
    abstract protected function getUiMetadata(): array;

    /**
     * Mapeo de estados internos a estados de publicación
     */
    protected static function mapStatus(string $status): string
    {
        return match($status) {
            // ServiceRequest estados
            'open' => 'active',
            'in_progress' => 'in_progress',
            'delivered' => 'delivered',      // NUEVO
            'completed' => 'completed',
            'disputed' => 'disputed',        // NUEVO
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            
            // RideRequest estados  
            'available' => 'active',
            'full' => 'full',                // NUEVO: Ride lleno pero no iniciado
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            
            default => 'active',
        };
    }

    /**
     * Verifica si puede transicionar a un nuevo estado
     */
    public function canTransitionTo(string $newStatus, ?\App\Models\User $user = null): bool
    {
        $current = $this->status;
        
        // Estados finales (no se pueden cambiar)
        if (in_array($current, ['completed', 'cancelled', 'expired'])) {
            return false;
        }

        // Lógica específica por modelo (opcional override)
        if (method_exists($this, 'validateStatusTransition')) {
            return $this->validateStatusTransition($current, $newStatus, $user);
        }

        // Reglas generales por defecto
        $allowed = [
            'open' => ['in_progress', 'cancelled', 'expired'],
            'available' => ['full', 'in_progress', 'cancelled', 'expired'],
            'full' => ['in_progress', 'cancelled'],
            'in_progress' => ['delivered', 'cancelled'],
            'delivered' => ['completed', 'disputed'],
            'disputed' => ['completed', 'cancelled'],
        ];

        return isset($allowed[$current]) && in_array($newStatus, $allowed[$current]);
    }

    /**
     * Transiciona de estado con validación
     */
    public function transitionTo(string $newStatus, ?\App\Models\User $user = null): bool
    {
        if (!$this->canTransitionTo($newStatus, $user)) {
            throw new \InvalidArgumentException(
                "No se puede cambiar de '{$this->status}' a '{$newStatus}'"
            );
        }

        $this->update(['status' => $newStatus]);
        return true;
    }
}
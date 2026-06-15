<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Publication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'publishable_id',
        'publishable_type',
        'title',
        'description',
        'category',
        'sub_category',
        'status',
        'offers_count',
        'views_count',
        'ui_metadata',
        'published_at',
    ];

    protected $casts = [
        'ui_metadata' => 'array',
        'published_at' => 'datetime',
    ];

    // Relación polimórfica
    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helpers para UI
    public function getTypeLabelAttribute(): string
    {
        return match($this->category) {
            'service' => 'Servicio',
            'ride' => 'Viaje',
            default => 'Publicación',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Activo',
            'in_progress' => 'En progreso',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'expired' => 'Expirado',
            default => $this->status,
        };
    }

    public function getBadgeAttribute(): array
    {
        return [
            'text' => $this->ui_metadata['badge_text'] ?? '',
            'color' => $this->ui_metadata['badge_color'] ?? 'gray',
        ];
    }
}

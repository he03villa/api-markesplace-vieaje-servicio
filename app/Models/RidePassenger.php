<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RidePassenger extends Model
{
    use HasFactory;

    protected $table = 'ride_passengers';

    protected $fillable = [
        'ride_request_id',
        'user_id',
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
        'special_requests',
        'no_show',
        'cancellation_reason',
    ];

    protected $casts = [
        'seats_reserved' => 'integer',
        'price_paid' => 'decimal:2',
        'price_per_seat' => 'decimal:2',
        'driver_rating' => 'integer',
        'passenger_rating' => 'integer',
        'no_show' => 'boolean',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relaciones
    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    // Métodos de utilidad
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function confirm(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsPaid(string $paymentMethod, ?string $paymentId = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_id' => $paymentId,
            'paid_at' => now(),
        ]);
    }

    public function rateDriver(int $rating, ?string $comment = null): void
    {
        $this->update([
            'driver_rating' => $rating,
            'driver_comment' => $comment,
        ]);
    }

    public function ratePassenger(int $rating, ?string $comment = null): void
    {
        $this->update([
            'passenger_rating' => $rating,
            'passenger_comment' => $comment,
        ]);
    }
}

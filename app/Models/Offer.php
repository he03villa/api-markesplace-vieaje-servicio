<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_request_id',
        'user_id',
        'price',
        'description',
        'estimated_time',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

     /**
     * Scope: Ofertas aceptadas
     */
    public function scopeAccepted(Builder $query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope: Ofertas pendientes
     */
    public function scopePending(Builder $query)
    {
        return $query->where('status', 'pending');
    }
}

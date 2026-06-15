<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ServiceRequestDelivery extends Model
{
    use HasFactory;

    protected $table = 'service_request_deliveries';

    protected $fillable = [
        'service_request_id',
        'worker_id',
        'completion_notes',
        'actual_hours',
        'evidence_images',
        'evidence_docs',
        'status',
        'client_feedback',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'evidence_images' => 'array',
        'evidence_docs'   => 'array',
        'actual_hours'    => 'decimal:2',
        'approved_at'     => 'datetime',
    ];

    public const STATUSES = [
        'pending'        => 'Pendiente de aprobacion',
        'approved'       => 'Aprobado',
        'rejected'       => 'Rechazado',
        'needs_revision' => 'Necesita correcciones',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }
    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Desconocido';
    }

    public function getEvidenceImageUrlsAttribute(): array
    {
        return array_map(fn($img) => Storage::disk('public')->url($img), $this->evidence_images ?? []);
    }

    public function getEvidenceDocUrlsAttribute(): array
    {
        return array_map(fn($doc) => [
            'name' => $doc['name'] ?? basename($doc['path']),
            'url'  => Storage::disk('public')->url($doc['path']),
            'mime' => $doc['mime'] ?? null,
        ], $this->evidence_docs ?? []);
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }
    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }
    public function getIsRejectedAttribute(): bool
    {
        return $this->status === 'rejected';
    }
    public function getNeedsRevisionAttribute(): bool
    {
        return $this->status === 'needs_revision';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    public function scopeForServiceRequest($query, int $id)
    {
        return $query->where('service_request_id', $id);
    }

    public function approve(int $clientId, ?string $feedback = null): void
    {
        $this->update(['status' => 'approved', 'approved_by' => $clientId, 'approved_at' => now(), 'client_feedback' => $feedback]);
    }

    public function reject(int $clientId, string $reason): void
    {
        $this->update(['status' => 'rejected', 'approved_by' => $clientId, 'approved_at' => now(), 'client_feedback' => $reason]);
    }

    public function requestRevision(int $clientId, string $feedback): void
    {
        $this->update(['status' => 'needs_revision', 'approved_by' => $clientId, 'client_feedback' => $feedback]);
    }

    public function deleteFiles(): void
    {
        foreach ($this->evidence_images ?? [] as $img) {
            if (Storage::disk('public')->exists($img)) Storage::disk('public')->delete($img);
        }
        foreach ($this->evidence_docs ?? [] as $doc) {
            $path = is_array($doc) ? ($doc['path'] ?? null) : $doc;
            if ($path && Storage::disk('public')->exists($path)) Storage::disk('public')->delete($path);
        }
    }

    protected static function booted(): void
    {
        static::deleting(fn($d) => $d->deleteFiles());
    }
}

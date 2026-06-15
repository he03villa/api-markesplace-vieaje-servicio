<?php

namespace App\Services;

use App\Exceptions\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestDelivery;
use App\Models\User;
use App\Utils\ImageUploader;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryService
{
    public function submitDelivery(ServiceRequest $sr, User $worker, array $data): ServiceRequestDelivery
    {
        $accepted = $sr->acceptedOffer;
        if (!$accepted || $accepted->user_id !== $worker->id) {
            throw new UnauthorizedActionException('No eres el worker asignado');
        }
        if (!$sr->isInProgress()) {
            throw new \InvalidArgumentException('La solicitud no esta en progreso');
        }

        $evidenceImages = [];
        if (!empty($data['evidence_images']) && is_array($data['evidence_images'])) {
            $evidenceImages = ImageUploader::storeMultiple(
                $data['evidence_images'], 'deliveries/images', $sr->id
            );
        }

        $evidenceDocs = [];
        if (!empty($data['evidence_docs']) && is_array($data['evidence_docs'])) {
            foreach ($data['evidence_docs'] as $doc) {
                $path = ImageUploader::store($doc, 'deliveries/docs', $sr->id);
                $evidenceDocs[] = [
                    'path' => $path,
                    'name' => $doc->getClientOriginalName(),
                    'mime' => $doc->getMimeType(),
                    'size' => $doc->getSize(),
                ];
            }
        }

        if ($sr->delivery) $sr->delivery->delete();

        $delivery = ServiceRequestDelivery::create([
            'service_request_id' => $sr->id,
            'worker_id'            => $worker->id,
            'completion_notes'     => $data['completion_notes'],
            'actual_hours'         => $data['actual_hours'] ?? null,
            'evidence_images'      => $evidenceImages,
            'evidence_docs'        => !empty($evidenceDocs) ? $evidenceDocs : null,
            'status'               => 'pending',
        ]);

        $sr->update(['status' => 'delivered', 'delivered_at' => now()]);

        return $delivery->load(['worker', 'serviceRequest']);
    }

    public function approveDelivery(ServiceRequestDelivery $delivery, User $client, ?string $feedback = null): ServiceRequestDelivery
    {
        $sr = $delivery->serviceRequest;
        if ($sr->user_id !== $client->id) throw new UnauthorizedActionException('Solo el cliente puede aprobar');
        if (!$sr->isDelivered()) throw new \InvalidArgumentException('La solicitud no esta entregada');

        $delivery->approve($client->id, $feedback);
        $sr->update(['status' => 'completed', 'completed_at' => now()]);
        $delivery->worker?->completeJob();

        return $delivery->fresh();
    }

    public function rejectDelivery(ServiceRequestDelivery $delivery, User $client, string $reason): ServiceRequestDelivery
    {
        $sr = $delivery->serviceRequest;
        if ($sr->user_id !== $client->id) throw new UnauthorizedActionException('Solo el cliente puede rechazar');
        if (!$sr->isDelivered()) throw new \InvalidArgumentException('La solicitud no esta entregada');

        $delivery->reject($client->id, $reason);
        return $delivery->fresh();
    }

    public function requestRevision(ServiceRequestDelivery $delivery, User $client, string $feedback): ServiceRequestDelivery
    {
        $sr = $delivery->serviceRequest;
        if ($sr->user_id !== $client->id) throw new UnauthorizedActionException('Solo el cliente puede solicitar revision');
        if (!$sr->isDelivered()) throw new \InvalidArgumentException('La solicitud no esta entregada');

        $delivery->requestRevision($client->id, $feedback);
        $sr->update(['status' => 'in_progress']);

        return $delivery->fresh();
    }

    public function getDelivery(ServiceRequest $sr): ?ServiceRequestDelivery
    {
        return $sr->delivery?->load(['worker', 'serviceRequest.user']);
    }

    public function getWorkerDeliveries(User $worker, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ServiceRequestDelivery::with(['serviceRequest.user', 'serviceRequest'])
            ->where('worker_id', $worker->id)->latest();
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        return $query->paginate($perPage);
    }

    public function getPendingApprovals(User $client, int $perPage = 15): LengthAwarePaginator
    {
        return ServiceRequestDelivery::with(['serviceRequest', 'worker'])
            ->whereHas('serviceRequest', fn($q) => $q->where('user_id', $client->id))
            ->pending()->latest()->paginate($perPage);
    }
}

<?php
namespace App\Services;

use App\Events\OfferAccepted;
use App\Events\OfferCreated;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Exceptions\ServiceRequestClosedException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Broadcast;

class OfferService
{
    public function createOffer(User $user, array $data): Offer
    {
        $serviceRequest = ServiceRequest::findOrFail($data['service_request_id']);

        if (!$serviceRequest->isOpen()) {
            throw new ServiceRequestClosedException();
        }

        $offer = $user->offers()->create([
            'service_request_id' => $data['service_request_id'],
            'price' => $data['price'],
            'description' => $data['description'],
            'estimated_time' => $data['estimated_time'] ?? null,
            'status' => 'pending', // ← Aquí explícito
        ]);
        $offer->load(['user', 'serviceRequest']);
        Broadcast::event(new OfferCreated($offer))->toOthers();

        return $offer;
    }

    public function acceptOffer(Offer $offer): Offer
    {
        $offer->update(['status' => 'accepted']);
        $offer->serviceRequest->update(['status' => 'in_progress']);

        $rejectedIds = $offer->serviceRequest->offers()
                ->where('id', '!=', $offer->id)
                ->where('status', 'pending')
                ->pluck('id')
                ->toArray();

        $offer->serviceRequest->offers()
            ->where('id', '!=', $offer->id)
            ->update(['status' => 'rejected']);

        $offer->load(['user', 'serviceRequest']);

        // Emitir evento en tiempo real
        Broadcast::event(new OfferAccepted($offer, $rejectedIds))->toOthers();

        return $offer->fresh();
    }

    public function getUserOffers(User $user): LengthAwarePaginator
    {
        return $user->offers()
            ->with('serviceRequest.user')
            ->latest()
            ->paginate(20);
    }

    public function findOffer(int $id, $with = []): Offer
    {
        if (count($with) > 0) {
            return Offer::with($with)->findOrFail($id);
        }
        return Offer::findOrFail($id);
    }

    public function getOfferByUser(User $user, int $id): Offer | null
    {
        return $user->offers()->where('service_request_id', $id)->first();
    }
}
<?php

namespace App\Observers;

use App\Models\Offer;

class OfferObserver
{
    public function created(Offer $offer): void
    {
        $offer->serviceRequest->syncPublication();
    }

    public function updated(Offer $offer): void
    {
        if ($offer->isDirty('status')) {
            $offer->serviceRequest->syncPublication();

            if ($offer->status === 'accepted') {
                $offer->serviceRequest->updateQuietly(['worker_id' => $offer->user_id]);
            }
        }
    }

    public function deleted(Offer $offer): void
    {
        $offer->serviceRequest->syncPublication();
    }
}

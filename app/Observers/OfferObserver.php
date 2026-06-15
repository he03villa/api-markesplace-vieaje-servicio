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
        // Solo sincronizar si cambió el status
        if ($offer->isDirty('status')) {
            $offer->serviceRequest->syncPublication();
        }
    }

    public function deleted(Offer $offer): void
    {
        $offer->serviceRequest->syncPublication();
    }
}

<?php

namespace App\Observers;
use App\Models\RidePassenger;

class RidePassengerObserver
{
    public function created(RidePassenger $passenger): void
    {
        $passenger->rideRequest?->syncPublication();
    }

    public function updated(RidePassenger $passenger): void
    {
        if ($passenger->isDirty('status')) {
            $passenger->rideRequest?->syncPublication();
        }
    }

    public function deleted(RidePassenger $passenger): void
    {
        $passenger->rideRequest?->syncPublication();
    }
}

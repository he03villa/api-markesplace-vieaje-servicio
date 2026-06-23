<?php

namespace App\Observers;

use App\Models\RideRequest;
use App\Services\GeolocationService;

class RideRequestObserver
{
    protected $geolocationService;

    public function __construct(GeolocationService $geolocationService)
    {
        $this->geolocationService = $geolocationService;
    }

    /**
     * Handle the RideRequest "created" event.
     */
    public function created(RideRequest $rideRequest): void
    {
        $this->geolocationService->assignLocationToRideRequest($rideRequest);
        $rideRequest->refresh();
    }

    /**
     * Handle the RideRequest "updated" event.
     */
    public function updated(RideRequest $rideRequest): void
    {
        // Solo actualizar si cambiaron las coordenadas
        if ($rideRequest->isDirty(['origin_lat', 'origin_lng', 'destination_lat', 'destination_lng'])) {
            $this->geolocationService->assignLocationToRideRequest($rideRequest);
        }
    }
}
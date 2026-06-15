<?php

namespace App\Observers;

use App\Models\ServiceRequest;
use App\Services\GeolocationService;

class ServiceRequestObserver
{
    protected $geolocationService;

    public function __construct(GeolocationService $geolocationService)
    {
        $this->geolocationService = $geolocationService;
    }

    /**
     * Handle the ServiceRequest "created" event.
     */
    public function created(ServiceRequest $serviceRequest): void
    {
        $this->geolocationService->assignLocationToServiceRequest($serviceRequest);
    }

    /**
     * Handle the ServiceRequest "updated" event.
     */
    public function updated(ServiceRequest $serviceRequest): void
    {
        // Solo actualizar si cambiaron las coordenadas
        if ($serviceRequest->isDirty(['latitude', 'longitude'])) {
            $this->geolocationService->assignLocationToServiceRequest($serviceRequest);
        }
    }
}
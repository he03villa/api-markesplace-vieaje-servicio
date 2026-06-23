<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RideRequest',
    description: 'Viaje compartido',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'driver_id', type: 'integer', example: 1),
        new OA\Property(property: 'origin_address', type: 'string', example: 'Calle Origen 123'),
        new OA\Property(property: 'origin_lat', type: 'number', format: 'float', example: 19.4326),
        new OA\Property(property: 'origin_lng', type: 'number', format: 'float', example: -99.1332),
        new OA\Property(property: 'destination_address', type: 'string', example: 'Calle Destino 456'),
        new OA\Property(property: 'destination_lat', type: 'number', format: 'float', example: 19.5326),
        new OA\Property(property: 'destination_lng', type: 'number', format: 'float', example: -99.2332),
        new OA\Property(property: 'departure_time', type: 'string', format: 'datetime', example: '2026-06-23T16:00:00Z'),
        new OA\Property(property: 'available_seats', type: 'integer', example: 3),
        new OA\Property(property: 'total_seats', type: 'integer', example: 3),
        new OA\Property(property: 'price_per_seat', type: 'number', format: 'float', example: 50),
        new OA\Property(property: 'status', type: 'string', example: 'available', enum: ['available', 'full', 'in_progress', 'completed', 'cancelled']),
        new OA\Property(property: 'vehicle_make', type: 'string', nullable: true, example: 'Toyota'),
        new OA\Property(property: 'vehicle_model', type: 'string', nullable: true, example: 'Corolla'),
        new OA\Property(property: 'vehicle_year', type: 'integer', nullable: true, example: 2020),
        new OA\Property(property: 'vehicle_color', type: 'string', nullable: true, example: 'Blanco'),
        new OA\Property(property: 'estimated_distance', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'driver', ref: '#/components/schemas/User'),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
    ],
    type: 'object'
)]
class RideRequestSchema
{
}

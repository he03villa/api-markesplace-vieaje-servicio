<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'JoinRideRequest',
    description: 'Solicitar unirse a un viaje',
    required: ['seats'],
    properties: [
        new OA\Property(property: 'seats', type: 'integer', example: 1, minimum: 1),
        new OA\Property(property: 'pickup_location', type: 'string', nullable: true, example: 'Esquina del parque'),
        new OA\Property(property: 'dropoff_location', type: 'string', nullable: true, example: 'Centro comercial'),
        new OA\Property(property: 'special_requests', type: 'string', nullable: true, example: 'Voy con equipaje'),
    ],
    type: 'object'
)]
class JoinRideRequest
{
}

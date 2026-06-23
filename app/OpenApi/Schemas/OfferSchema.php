<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Offer',
    description: 'Oferta a una solicitud de servicio',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'service_request_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 2),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 350),
        new OA\Property(property: 'description', type: 'string', example: 'Yo puedo hacerlo'),
        new OA\Property(property: 'estimated_time', type: 'string', nullable: true, example: '2 horas'),
        new OA\Property(property: 'status', type: 'string', example: 'pending', enum: ['pending', 'accepted', 'rejected']),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
    ],
    type: 'object'
)]
class OfferSchema
{
}

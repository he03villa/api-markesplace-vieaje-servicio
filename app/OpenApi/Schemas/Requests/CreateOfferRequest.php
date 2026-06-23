<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateOfferRequest',
    description: 'Crear oferta a un servicio',
    required: ['service_request_id', 'price', 'description'],
    properties: [
        new OA\Property(property: 'service_request_id', type: 'integer', example: 1),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 350),
        new OA\Property(property: 'description', type: 'string', example: 'Yo puedo hacerlo'),
        new OA\Property(property: 'estimated_time', type: 'string', nullable: true, example: '2 horas'),
    ],
    type: 'object'
)]
class CreateOfferRequest
{
}

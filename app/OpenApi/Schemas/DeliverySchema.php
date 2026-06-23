<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Delivery',
    description: 'Entrega de servicio',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'service_request_id', type: 'integer', example: 1),
        new OA\Property(property: 'worker_id', type: 'integer', example: 2),
        new OA\Property(property: 'description', type: 'string', example: 'Trabajo terminado'),
        new OA\Property(property: 'status', type: 'string', example: 'pending', enum: ['pending', 'approved', 'rejected', 'revision']),
        new OA\Property(property: 'evidence', type: 'array', items: new OA\Items(type: 'string'), description: 'URLs de evidencia'),
        new OA\Property(property: 'client_comment', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
    ],
    type: 'object'
)]
class DeliverySchema
{
}

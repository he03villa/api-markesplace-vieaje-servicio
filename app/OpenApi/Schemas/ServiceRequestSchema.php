<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ServiceRequest',
    description: 'Solicitud de servicio',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Necesito un plomero'),
        new OA\Property(property: 'description', type: 'string', example: 'Tengo una fuga en la cocina'),
        new OA\Property(property: 'category', type: 'string', example: 'plomeria'),
        new OA\Property(property: 'address', type: 'string', example: 'Calle Principal 123'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 19.4326),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -99.1332),
        new OA\Property(property: 'budget_min', type: 'number', format: 'float', nullable: true, example: 100),
        new OA\Property(property: 'budget_max', type: 'number', format: 'float', nullable: true, example: 500),
        new OA\Property(property: 'deadline', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'open', enum: ['open', 'in_progress', 'delivered', 'completed', 'disputed', 'cancelled', 'expired']),
        new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'datetime'),
    ],
    type: 'object'
)]
class ServiceRequestSchema
{
}

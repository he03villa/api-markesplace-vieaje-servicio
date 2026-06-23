<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateServiceRequest',
    description: 'Crear solicitud de servicio',
    required: ['title', 'description', 'category', 'address', 'latitude', 'longitude'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Necesito un plomero'),
        new OA\Property(property: 'description', type: 'string', example: 'Tengo una fuga en la cocina'),
        new OA\Property(property: 'category', type: 'string', example: 'plomeria'),
        new OA\Property(property: 'address', type: 'string', example: 'Calle Principal 123'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 19.4326),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -99.1332),
        new OA\Property(property: 'budget_min', type: 'number', format: 'float', nullable: true, example: 100),
        new OA\Property(property: 'budget_max', type: 'number', format: 'float', nullable: true, example: 500),
        new OA\Property(property: 'deadline', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object'
)]
class CreateServiceRequest
{
}

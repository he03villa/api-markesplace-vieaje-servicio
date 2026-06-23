<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Publication',
    description: 'Publicación polimórfica (servicio o viaje)',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'type', type: 'string', example: 'service', enum: ['service', 'ride']),
        new OA\Property(property: 'title', type: 'string', example: 'Necesito un plomero'),
        new OA\Property(property: 'description', type: 'string', example: 'Tengo una fuga...'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'status_label', type: 'string', example: 'Activo'),
        new OA\Property(property: 'badge', type: 'string', nullable: true),
        new OA\Property(property: 'offers_count', type: 'integer', example: 3),
        new OA\Property(property: 'views_count', type: 'integer', example: 15),
        new OA\Property(property: 'published_at', type: 'string', format: 'datetime'),
        new OA\Property(property: 'ui_metadata', type: 'object', nullable: true),
    ],
    type: 'object'
)]
class PublicationSchema
{
}

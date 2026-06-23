<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Review',
    description: 'Reseña de usuario',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'rating', type: 'integer', example: 5, minimum: 1, maximum: 5),
        new OA\Property(property: 'comment', type: 'string', example: 'Excelente servicio'),
        new OA\Property(property: 'category', type: 'string', nullable: true, example: 'service'),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
        new OA\Property(property: 'reviewer', ref: '#/components/schemas/User'),
    ],
    type: 'object'
)]
class ReviewSchema
{
}

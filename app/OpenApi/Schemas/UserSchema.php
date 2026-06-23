<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    description: 'Usuario del sistema',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@example.com'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: 'https://example.com/avatar.jpg'),
        new OA\Property(property: 'rating', type: 'number', format: 'float', example: 4.5),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'datetime', nullable: true),
        new OA\Property(property: 'has_notification', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
    ],
    type: 'object'
)]
class UserSchema
{
}

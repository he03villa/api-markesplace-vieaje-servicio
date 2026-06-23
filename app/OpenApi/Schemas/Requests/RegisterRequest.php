<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegisterRequest',
    description: 'Registro de nuevo usuario',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
    ],
    type: 'object'
)]
class RegisterRequest
{
}

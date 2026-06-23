<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LoginRequest',
    description: 'Credenciales de inicio de sesión',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
    ],
    type: 'object'
)]
class LoginRequest
{
}

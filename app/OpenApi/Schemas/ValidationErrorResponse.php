<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ValidationErrorResponse',
    description: 'Respuesta de error de validación',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Errores de validación'),
        new OA\Property(property: 'errors', type: 'object', example: ['field' => ['El campo es requerido']]),
    ],
    type: 'object'
)]
class ValidationErrorResponse
{
}

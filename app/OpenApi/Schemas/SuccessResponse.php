<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SuccessResponse',
    description: 'Respuesta exitosa estándar',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Operación exitosa'),
        new OA\Property(property: 'data', description: 'Datos de la respuesta', nullable: true),
    ],
    type: 'object'
)]
class SuccessResponse
{
}

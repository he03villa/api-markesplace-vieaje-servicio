<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Message',
    description: 'Mensaje de chat',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
        new OA\Property(property: 'sender_id', type: 'integer', example: 1),
        new OA\Property(property: 'content', type: 'string', example: 'Hola, ¿sigues disponible?'),
        new OA\Property(property: 'type', type: 'string', example: 'text', enum: ['text', 'image', 'file']),
        new OA\Property(property: 'read_at', type: 'string', format: 'datetime', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
        new OA\Property(property: 'sender', ref: '#/components/schemas/User'),
    ],
    type: 'object'
)]
class MessageSchema
{
}

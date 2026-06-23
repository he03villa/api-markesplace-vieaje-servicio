<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SendMessageRequest',
    description: 'Enviar mensaje en una conversación',
    required: ['conversation_id', 'content'],
    properties: [
        new OA\Property(property: 'conversation_id', type: 'integer', example: 1),
        new OA\Property(property: 'content', type: 'string', example: 'Hola, ¿sigues disponible?'),
        new OA\Property(property: 'type', type: 'string', example: 'text', enum: ['text', 'image', 'file']),
    ],
    type: 'object'
)]
class SendMessageRequest
{
}

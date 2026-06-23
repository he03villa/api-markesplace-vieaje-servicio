<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Conversation',
    description: 'Conversación entre dos usuarios',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_a_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_b_id', type: 'integer', example: 2),
        new OA\Property(property: 'last_message_at', type: 'string', format: 'datetime', nullable: true),
        new OA\Property(property: 'last_message', type: 'string', nullable: true, example: 'Hola, ¿disponible?'),
        new OA\Property(property: 'unread_count', type: 'integer', example: 0),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
    ],
    type: 'object'
)]
class ConversationSchema
{
}

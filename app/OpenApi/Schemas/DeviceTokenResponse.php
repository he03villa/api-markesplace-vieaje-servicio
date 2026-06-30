<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DeviceTokenResponse',
    description: 'Token de dispositivo registrado',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'device_token', type: 'string', example: 'fcm-token-abc123'),
        new OA\Property(property: 'platform', type: 'string', example: 'android'),
        new OA\Property(property: 'device_name', type: 'string', example: 'Mi Pixel 7'),
        new OA\Property(property: 'last_used_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
        new OA\Property(property: 'created_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
    ],
    type: 'object'
)]
class DeviceTokenResponse
{
}

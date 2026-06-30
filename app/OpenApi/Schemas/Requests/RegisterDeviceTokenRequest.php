<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegisterDeviceTokenRequest',
    description: 'Registrar token de dispositivo para notificaciones push',
    required: ['device_token'],
    properties: [
        new OA\Property(property: 'device_token', type: 'string', example: 'fcm-token-abc123'),
        new OA\Property(property: 'platform', type: 'string', enum: ['web', 'android', 'ios'], example: 'android'),
        new OA\Property(property: 'device_name', type: 'string', example: 'Mi Pixel 7'),
    ],
    type: 'object'
)]
class RegisterDeviceTokenRequest
{
}

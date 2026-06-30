<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Marketplace Viaje Servicio API',
    description: 'API de marketplace para servicios y viajes compartidos'
)]
#[OA\Server(
    url: SWAGGER_SERVER_HOST,
    description: 'API server'
)]
#[OA\SecurityScheme(
    securityScheme: 'jwt',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token JWT obtenido al iniciar sesión'
)]
#[OA\Tag(name: 'Auth', description: 'Autenticación y gestión de usuarios')]
#[OA\Tag(name: 'Perfil', description: 'Perfil de usuario, publicaciones y asignaciones')]
#[OA\Tag(name: 'Servicios', description: 'Solicitudes de servicio y ofertas')]
#[OA\Tag(name: 'Entregas', description: 'Flujo de entrega y aprobación de servicios')]
#[OA\Tag(name: 'Viajes', description: 'Viajes compartidos y pasajeros')]
#[OA\Tag(name: 'Chat', description: 'Mensajería en tiempo real')]
#[OA\Tag(name: 'Reseñas', description: 'Reseñas y valoraciones')]
#[OA\Tag(name: 'Notificaciones', description: 'Tokens de dispositivo y notificaciones push')]
class Spec
{
}

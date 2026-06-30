<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterDeviceTokenRequest;
use App\Http\Requests\SubscribeTopicRequest;
use App\Http\Requests\UnsubscribeTopicRequest;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class FCMController extends Controller
{
    use \App\Traits\ApiResponseTrait;

    public function __construct(
        protected FCMService $fcmService
    ) {}

    #[OA\Post(
        path: '/api/auth/device-token',
        tags: ['Notificaciones'],
        summary: 'Registrar o actualizar token de dispositivo',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterDeviceTokenRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token registrado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Token registrado correctamente'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'user_id', type: 'integer', example: 1),
                            new OA\Property(property: 'device_token', type: 'string', example: 'fcm-token-abc123'),
                            new OA\Property(property: 'platform', type: 'string', example: 'android'),
                            new OA\Property(property: 'device_name', type: 'string', example: 'Mi Pixel 7'),
                            new OA\Property(property: 'last_used_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
                            new OA\Property(property: 'created_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
                            new OA\Property(property: 'updated_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Errores de validación'),
                        new OA\Property(property: 'errors', type: 'object', example: ['device_token' => ['El token del dispositivo es obligatorio.']]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Error al registrar el token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error al registrar el token'),
                    ]
                )
            ),
        ]
    )]
    public function register(RegisterDeviceTokenRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $token = DeviceToken::updateOrCreate(
                ['device_token' => $request->device_token],
                [
                    'user_id' => $user->id,
                    'platform' => $request->platform,
                    'device_name' => $request->device_name,
                    'last_used_at' => now(),
                ]
            );

            return $this->successResponse($token, 'Token registrado correctamente');
        } catch (\Exception $e) {
            Log::error('Error registering device token', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al registrar el token', 500);
        }
    }

    #[OA\Delete(
        path: '/api/auth/device-token',
        tags: ['Notificaciones'],
        summary: 'Eliminar token de dispositivo',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['device_token'],
                properties: [
                    new OA\Property(property: 'device_token', type: 'string', example: 'fcm-token-abc123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token eliminado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Token eliminado correctamente'),
                        new OA\Property(property: 'data', example: null),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Token no encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Token no encontrado'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Errores de validación'),
                        new OA\Property(property: 'errors', type: 'object', example: ['device_token' => ['El campo device token es requerido.']]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Error al eliminar el token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error al eliminar el token'),
                    ]
                )
            ),
        ]
    )]
    public function unregister(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_token' => 'required|string',
            ]);

            $deleted = DeviceToken::where('user_id', $request->user()->id)
                ->where('device_token', $request->device_token)
                ->delete();

            if ($deleted) {
                return $this->successResponse(null, 'Token eliminado correctamente');
            }

            return $this->notFoundResponse('Token no encontrado');
        } catch (\Exception $e) {
            Log::error('Error unregistering device token', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al eliminar el token', 500);
        }
    }

    #[OA\Get(
        path: '/api/auth/device-token',
        tags: ['Notificaciones'],
        summary: 'Listar tokens del usuario autenticado',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tokens obtenidos correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Tokens obtenidos correctamente'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'device_token', type: 'string', example: 'fcm-token-abc123'),
                                new OA\Property(property: 'platform', type: 'string', example: 'android'),
                                new OA\Property(property: 'device_name', type: 'string', example: 'Mi Pixel 7'),
                                new OA\Property(property: 'last_used_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
                                new OA\Property(property: 'created_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', example: '2026-06-30T08:34:00.000000Z'),
                            ]
                        )),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Error al obtener los tokens',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error al obtener los tokens'),
                    ]
                )
            ),
        ]
    )]
    public function listTokens(Request $request): JsonResponse
    {
        try {
            $tokens = DeviceToken::where('user_id', $request->user()->id)->get();

            return $this->successResponse($tokens, 'Tokens obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error listing device tokens', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al obtener los tokens', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/device-token/subscribe',
        tags: ['Notificaciones'],
        summary: 'Suscribir tokens del usuario a un tópico FCM',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['topic'],
                properties: [
                    new OA\Property(property: 'topic', type: 'string', example: 'service-requests'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Suscripción al tópico exitosa',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Suscripción al tópico exitosa'),
                        new OA\Property(property: 'data', example: null),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'No hay tokens registrados',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No hay tokens registrados para este usuario'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Errores de validación'),
                        new OA\Property(property: 'errors', type: 'object', example: ['topic' => ['El tópico es obligatorio.']]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Error al suscribirse al tópico',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error al suscribirse al tópico'),
                    ]
                )
            ),
        ]
    )]
    public function subscribeToTopic(SubscribeTopicRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokens = DeviceToken::where('user_id', $user->id)->pluck('device_token')->toArray();

            if (empty($tokens)) {
                return $this->errorResponse('No hay tokens registrados para este usuario', 400);
            }

            $result = $this->fcmService->subscribeToTopic($tokens, $request->topic);

            if ($result['success']) {
                return $this->successResponse(null, 'Suscripción al tópico exitosa');
            }

            return $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('Error subscribing to topic', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al suscribirse al tópico', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/device-token/unsubscribe',
        tags: ['Notificaciones'],
        summary: 'Desuscribir tokens del usuario de un tópico FCM',
        security: [['jwt' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['topic'],
                properties: [
                    new OA\Property(property: 'topic', type: 'string', example: 'service-requests'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Desuscripción del tópico exitosa',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Desuscripción del tópico exitosa'),
                        new OA\Property(property: 'data', example: null),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'No hay tokens registrados',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No hay tokens registrados para este usuario'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Errores de validación'),
                        new OA\Property(property: 'errors', type: 'object', example: ['topic' => ['El tópico es obligatorio.']]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Error al desuscribirse del tópico',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error al desuscribirse del tópico'),
                    ]
                )
            ),
        ]
    )]
    public function unsubscribeFromTopic(UnsubscribeTopicRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokens = DeviceToken::where('user_id', $user->id)->pluck('device_token')->toArray();

            if (empty($tokens)) {
                return $this->errorResponse('No hay tokens registrados para este usuario', 400);
            }

            $result = $this->fcmService->unsubscribeFromTopic($tokens, $request->topic);

            if ($result['success']) {
                return $this->successResponse(null, 'Desuscripción del tópico exitosa');
            }

            return $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('Error unsubscribing from topic', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al desuscribirse del tópico', 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/notifications/test/{user}',
        tags: ['Notificaciones'],
        summary: 'Enviar notificación de prueba a un usuario por ID',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'body'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Notificación de prueba'),
                    new OA\Property(property: 'body', type: 'string', example: 'Este es un mensaje de prueba'),
                    new OA\Property(property: 'data', type: 'object', example: ['type' => 'test', 'action' => 'none']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Notificación enviada',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notificación enviada'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'success', type: 'boolean', example: true),
                            new OA\Property(property: 'success_count', type: 'integer', example: 2),
                            new OA\Property(property: 'failure_count', type: 'integer', example: 0),
                            new OA\Property(property: 'failures', type: 'array', items: new OA\Items(type: 'object')),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'El usuario no tiene tokens registrados',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'El usuario 5 no tiene tokens registrados'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Error de validación',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Errores de validación'),
                        new OA\Property(property: 'errors', type: 'object', example: ['title' => ['El campo title es obligatorio.']]),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Error al enviar la notificación',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Error al enviar la notificación'),
                    ]
                )
            ),
        ]
    )]
    public function testNotification(Request $request, User $user): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'body' => 'required|string',
            ]);

            $tokens = DeviceToken::where('user_id', $user->id)->pluck('device_token')->toArray();

            if (empty($tokens)) {
                return $this->errorResponse("El usuario {$user->id} no tiene tokens registrados");
            }

            $result = $this->fcmService->sendToMultipleTokens($tokens, [
                'title' => $request->title,
                'body' => $request->body,
                'data' => $request->data ?? [],
            ]);

            return $this->successResponse($result, 'Notificación enviada');
        } catch (\Exception $e) {
            Log::error('Error sending test notification', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return $this->errorResponse('Error al enviar la notificación', 500);
        }
    }
}

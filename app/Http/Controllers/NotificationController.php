<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Notificaciones', description: 'Notificaciones push y en-app')]
class NotificationController extends Controller
{
    use \App\Traits\ApiResponseTrait;

    #[OA\Get(
        path: '/api/notifications',
        tags: ['Notificaciones'],
        summary: 'Listar notificaciones del usuario',
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de notificaciones',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notificaciones obtenidas correctamente'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'notifications', type: 'array', items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'type', type: 'string', example: 'offer_created'),
                                    new OA\Property(property: 'title', type: 'string', example: 'Nueva oferta'),
                                    new OA\Property(property: 'body', type: 'string', example: 'Alguien envió una oferta para tu solicitud'),
                                    new OA\Property(property: 'data', type: 'object', nullable: true),
                                    new OA\Property(property: 'action_url', type: 'string', nullable: true),
                                    new OA\Property(property: 'read_at', type: 'string', format: 'datetime', nullable: true),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'datetime'),
                                ]
                            )),
                            new OA\Property(property: 'unread_count', type: 'integer', example: 3),
                            new OA\Property(property: 'total', type: 'integer', example: 10),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 1),
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 50);

        $notifications = $request->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $notifications->getCollection()->transform(function ($notification) {
            $data = $notification->data;

            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? 'generic',
                'title' => $data['title'] ?? '',
                'body' => $data['body'] ?? '',
                'data' => $data['data'] ?? null,
                'action_url' => $data['action_url'] ?? null,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
            ];
        });

        return $this->successResponse([
            'notifications' => $notifications->items(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'total' => $notifications->total(),
            'per_page' => $notifications->perPage(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ], 'Notificaciones obtenidas correctamente');
    }

    #[OA\Get(
        path: '/api/notifications/unread-count',
        tags: ['Notificaciones'],
        summary: 'Obtener cantidad de notificaciones no leídas',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Conteo de no leídas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Conteo obtenido'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'unread_count', type: 'integer', example: 3),
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return $this->successResponse([
            'unread_count' => $count,
        ], 'Conteo obtenido');
    }

    #[OA\Patch(
        path: '/api/notifications/{id}/read',
        tags: ['Notificaciones'],
        summary: 'Marcar notificación como leída',
        security: [['jwt' => []]],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'string'), description: 'UUID de la notificación'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Marcada como leída',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notificación marcada como leída'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Notificación no encontrada'),
        ]
    )]
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return $this->notFoundResponse('Notificación no encontrada');
        }

        $notification->markAsRead();

        return $this->successResponse(null, 'Notificación marcada como leída');
    }

    #[OA\Post(
        path: '/api/notifications/read-all',
        tags: ['Notificaciones'],
        summary: 'Marcar todas las notificaciones como leídas',
        security: [['jwt' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Todas marcadas como leídas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Todas las notificaciones fueron marcadas como leídas'),
                        new OA\Property(property: 'data', properties: [
                            new OA\Property(property: 'marked_count', type: 'integer', example: 5),
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse([
            'marked_count' => $count,
        ], 'Todas las notificaciones fueron marcadas como leídas');
    }
}

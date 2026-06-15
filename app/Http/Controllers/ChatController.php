<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\TypingRequest;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\AttachmentService;
use App\Services\ChatService;
use App\Services\UserService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ChatService       $chatService,
        protected AttachmentService $attachmentService,
        protected UserService       $userService
    ) {}

    /**
     * GET /api/chat/conversations
     */
    public function conversations(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->chatService->getConversations($request->user()->id);
 
        return ConversationResource::collection($conversations);
    }

    /**
     * GET /api/chat/conversations/{conversation}
     */
    public function showConversation(Request $request, int $conversationId): ConversationResource
    {
        $conversation = $this->chatService->getConversation($conversationId, $request->user()->id);
 
        return new ConversationResource($conversation);
    }

    /**
     * GET /api/chat/conversations/{conversation}/messages
     */
    public function messages(Request $request, int $conversationId): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);
 
        $messages = $this->chatService->getMessages(
            conversationId: $conversationId,
            userId:         $request->user()->id,
            perPage:        (int) $request->get('per_page', 30),
        );
 
        return MessageResource::collection($messages);
    }

    /**
     * POST /api/chat/messages
     * multipart/form-data: body (texto) + files[] (adjuntos opcionales)
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $message = $this->chatService->sendMessage(
                senderId:   $request->user()->id,
                receiverId: (int) $request->receiver_id,
                body:       $request->input('body'),
                files:      $request->file('files', []),
            );
    
            $dataResponse = [
                'message' => new MessageResource($message['message']),
                'conversation' => new ConversationResource($message['conversation']),
            ];
     
            return $this->successResponse($dataResponse);
        } catch (\Exception $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/chat/messages/{message}
     * Solo el emisor puede eliminar su propio mensaje.
     */
    public function destroyMessage(Request $request, int $messageId): JsonResponse
    {
        $message = Message::with('attachments')->findOrFail($messageId);
 
        abort_if($message->sender_id !== $request->user()->id, 403, 'No puedes eliminar este mensaje.');
 
        $this->attachmentService->deleteForMessage($message);
        $message->delete();
 
        return response()->json(['message' => 'Mensaje eliminado.']);
    }

    /**
     * DELETE /api/chat/attachments/{attachment}
     */
    public function destroyAttachment(Request $request, int $attachmentId): JsonResponse
    {
        $attachment = MessageAttachment::with('message')->findOrFail($attachmentId);
 
        abort_if(
            $attachment->message->sender_id !== $request->user()->id,
            403,
            'No puedes eliminar este adjunto.'
        );
 
        $message        = $attachment->message;
        $remainingCount = $message->attachments()->count();
 
        $this->attachmentService->deleteAttachment($attachment);
 
        if ($remainingCount === 1 && is_null($message->body)) {
            $message->delete();
            return response()->json(['message' => 'Adjunto y mensaje eliminados.']);
        }
 
        return response()->json(['message' => 'Adjunto eliminado.']);
    }

    /**
     * PATCH /api/chat/conversations/{conversation}/read
     */
    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        $affected = $this->chatService->markAsRead($conversationId, $request->user()->id);
 
        return response()->json([
            'message'  => 'Mensajes marcados como leídos.',
            'affected' => $affected,
        ]);
    }

    /**
     * POST /api/chat/typing
     */
    public function typing(TypingRequest $request): JsonResponse
    {
        $this->chatService->broadcastTyping(
            conversationId: (int) $request->conversation_id,
            typingUserId:   $request->user()->id,
            isTyping:       (bool) $request->is_typing,
        );
 
        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/chat/conversations/{conversation}
     */
    public function showConversationUsers(Request $request, int $userId): JsonResponse
    {
        try {
            $conversation = $this->chatService->getConversationUsers($request->user()->id, $userId);
            return $this->successResponse(new ConversationResource($conversation), 'Conversación obtenida');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error($e->getMessage());
            $user = $this->userService->getUser($userId);
            $dataRespuesta = [
                'id'              => null,
                'contact'         => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'avatar' => $user->avatar_url ?? null,
                ],
                'last_message'    => null,
                'unread_count'    => 0,
                'last_message_at' => null,
            ];
            return $this->successResponse($dataRespuesta, 'Conversación obtenida');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
 
    }
}

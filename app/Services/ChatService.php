<?php

namespace App\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(protected AttachmentService $attachmentService) {}

    // =========================================================================
    //  CONVERSATIONS
    // =========================================================================

    /**
     * Inbox del usuario — solo toca conversations + last_message.
     * Sin COUNT(*): usa los contadores desnormalizados.
     */
    public function getConversations(int $userId): Collection
    {
        return Conversation::with([
            'userA:id,name',
            'userB:id,name',
            'lastMessage:id,body,sender_id,created_at',
            'lastMessage.attachments:id,message_id,type,original_name',
        ])
            ->where('user_a_id', $userId)
            ->orWhere('user_b_id', $userId)
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Detalle de una conversación (valida pertenencia del usuario).
     */
    public function getConversation(int $conversationId, int $userId): Conversation
    {
        return Conversation::with([
            'userA:id,name',
            'userB:id,name',
        ])
            ->where('id', $conversationId)
            ->where(
                fn($q) => $q
                    ->where('user_a_id', $userId)
                    ->orWhere('user_b_id', $userId)
            )
            ->firstOrFail();
    }

    // =========================================================================
    //  MESSAGES
    // =========================================================================

    /**
     * Historial paginado — solo toca la tabla messages.
     */
    public function getMessages(int $conversationId, int $userId, int $perPage = 30): LengthAwarePaginator
    {
        $this->getConversation($conversationId, $userId);

        return Message::with([
            'sender:id,name',
            'attachments',
        ])
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    /**
     * Envía un mensaje con adjuntos opcionales.
     *
     * @param  UploadedFile[]  $files
     */
    public function sendMessage(
        int     $senderId,
        int     $receiverId,
        ?string $body,
        array   $files = [],
    ) {
        abort_if(
            empty(trim((string) $body)) && empty($files),
            422,
            'El mensaje debe tener texto o al menos un adjunto.'
        );

        return DB::transaction(function () use ($senderId, $receiverId, $body, $files) {
            $conversation = Conversation::findOrCreateFor($senderId, $receiverId);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $senderId,
                'body'            => $body ?: null,
            ]);

            if (! empty($files)) {
                $this->attachmentService->attachToMessage($message, $files);
            }

            $conversation->stampNewMessage($message, $receiverId);

            $message->load(['sender:id,name', 'attachments']);

            MessageSent::dispatch($message, $conversation);

            $receiver = User::find($receiverId);
            if ($receiver && $senderId !== $receiverId) {
                $sender = User::find($senderId);
                $preview = mb_substr(strip_tags($body ?? ''), 0, 100);
                $attachmentText = !empty($files) ? ' 📎' : '';
                $receiver->notify(new PushNotification(
                    type: 'new_message',
                    title: $sender?->name ?? 'Nuevo mensaje',
                    body: $preview ? "{$preview}{$attachmentText}" : 'Te envió un archivo' . $attachmentText,
                    data: ['conversation_id' => $conversation->id, 'sender_id' => $senderId],
                    actionUrl: "/chat/{$conversation->id}",
                ));
            }

            return ['message' => $message, 'conversation' => $conversation];
        });
    }

    /**
     * Marca como leídos los mensajes recibidos y resetea el contador.
     */
    public function markAsRead(int $conversationId, int $userId): int
    {
        $conversation = $this->getConversation($conversationId, $userId);

        $affected = Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($affected > 0) {
            $conversation->resetUnreadFor($userId);
            MessageRead::dispatch($conversationId, $userId);
        }

        return $affected;
    }

    // =========================================================================
    //  TYPING
    // =========================================================================

    public function broadcastTyping(int $conversationId, int $typingUserId, bool $isTyping): void
    {
        UserTyping::dispatch($conversationId, $typingUserId, $isTyping);
    }

    public function getConversationUsers(int $userAId, int $userBId): Conversation
    {
        [$a, $b] = $userAId < $userBId ? [$userAId, $userBId] : [$userBId, $userAId];

        Log::info('query ejecutada', ['a' => $a, 'b' => $b]);

        return Conversation::with(['userA:id,name', 'userB:id,name'])
            ->where('user_a_id', $a)
            ->where('user_b_id', $b)
            ->firstOrFail();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_a_id',
        'user_b_id',
        'last_message_id',
        'last_message_at',
        'unread_a',
        'unread_b',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Devuelve el otro participante distinto de $userId.
     */
    public function contactFor(int $userId): User
    {
        return $this->user_a_id === $userId ? $this->userB : $this->userA;
    }

    /**
     * Contador de no leídos para un usuario específico.
     */
    public function unreadFor(int $userId): int
    {
        return $this->user_a_id === $userId ? $this->unread_a : $this->unread_b;
    }

    /**
     * Incrementa el contador del receptor y actualiza el último mensaje.
     */
    public function stampNewMessage(Message $message, int $receiverId): void
    {
        $isReceiverA = $this->user_a_id === $receiverId;
        $unreadA     = (int) ($this->unread_a ?? 0);
        $unreadB     = (int) ($this->unread_b ?? 0);

        $this->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
            'unread_a'        => $isReceiverA ? $unreadA + 1 : $unreadA,
            'unread_b'        => $isReceiverA ? $unreadB     : $unreadB + 1,
        ]);
    }

    /**
     * Resetea el contador de no leídos para un usuario.
     */
    public function resetUnreadFor(int $userId): void
    {
        $this->update(
            $this->user_a_id === $userId
                ? ['unread_a' => 0]
                : ['unread_b' => 0]
        );
    }

    // ── Static factory ───────────────────────────────────────────────────────

    /**
     * firstOrCreate normalizado: user_a siempre es el menor ID.
     */
    public static function findOrCreateFor(int $userX, int $userY): self
    {
        [$a, $b] = $userX < $userY ? [$userX, $userY] : [$userY, $userX];

        return self::firstOrCreate(
            ['user_a_id' => $a, 'user_b_id' => $b]
        );
    }
}
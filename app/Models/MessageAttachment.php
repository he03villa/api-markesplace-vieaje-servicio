<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'type',
        'path',
        'disk',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'duration_seconds',
    ];

    protected $casts = [
        'size'             => 'integer',
        'width'            => 'integer',
        'height'           => 'integer',
        'duration_seconds' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getUrlAttribute(): string
    {
        return $this->disk === 's3'
            ? Storage::disk('s3')->temporaryUrl($this->path, now()->addMinutes(60))
            : Storage::disk('public')->url($this->path);
    }

    public function getHumanSizeAttribute(): string
    {
        $kb = $this->size / 1024;
        if ($kb < 1024) return round($kb, 1) . ' KB';
        return round($kb / 1024, 1) . ' MB';
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public static function typeFromMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/')                => 'image',
            str_starts_with($mime, 'audio/')                => 'audio',
            $mime === 'video/webm'                          => 'audio', // 👈 WebM de audio grabado
            str_starts_with($mime, 'video/')                => 'video',
            $mime === 'application/pdf'                     => 'document',
            in_array($mime, [
                'application/zip',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])                                              => 'document',
            default                                         => 'file',
        };
    }
}

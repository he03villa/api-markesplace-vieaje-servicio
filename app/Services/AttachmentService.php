<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    private const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm', 'audio/mp4',
        'video/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'text/plain',
    ];

    /**
     * Valida, sube y persiste uno o varios archivos para un mensaje.
     *
     * @param  UploadedFile[]  $files
     * @return MessageAttachment[]
     */
    public function attachToMessage(Message $message, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $this->validateFile($file);
            $attachments[] = $this->processFile($message, $file);
        }

        return $attachments;
    }

    /**
     * Elimina del storage y de la DB todos los adjuntos de un mensaje.
     */
    public function deleteForMessage(Message $message): void
    {
        $message->attachments->each(fn ($a) => $this->deleteAttachment($a));
    }

    /**
     * Elimina un adjunto individual.
     */
    public function deleteAttachment(MessageAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function validateFile(UploadedFile $file): void
    {
        abort_if(
            $file->getSize() > self::MAX_SIZE_BYTES,
            422,
            "El archivo '{$file->getClientOriginalName()}' supera el límite de 20 MB."
        );

        abort_if(
            ! in_array($file->getMimeType(), self::ALLOWED_MIMES, true),
            422,
            "El tipo '{$file->getMimeType()}' no está permitido."
        );
    }

    private function processFile(Message $message, UploadedFile $file): MessageAttachment
    {
        $mime = $file->getMimeType();
        $type = MessageAttachment::typeFromMime($mime);
        $disk = config('chat.attachment_disk', 'public');
        $path = $this->store($file, $type, $disk);

        $data = [
            'message_id'    => $message->id,
            'type'          => $type,
            'path'          => $path,
            'disk'          => $disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $mime,
            'size'          => $file->getSize(),
        ];

        if ($type === 'image') {
            $data += $this->imageMetadata($file);
        }

        return MessageAttachment::create($data);
    }

    private function store(UploadedFile $file, string $type, string $disk): string
    {
        $folder = "chat/{$type}s/" . now()->format('Y/m');
        return $file->store($folder, ['disk' => $disk]);
    }

    private function imageMetadata(UploadedFile $file): array
    {
        try {
            if (class_exists(\Intervention\Image\Facades\Image::class)) {
                $img = \Intervention\Image\Facades\Image::make($file->getRealPath());
                return ['width' => $img->width(), 'height' => $img->height()];
            }

            [$w, $h] = getimagesize($file->getRealPath()) ?: [null, null];
            return ['width' => $w, 'height' => $h];
        } catch (\Throwable) {
            return ['width' => null, 'height' => null];
        }
    }
}
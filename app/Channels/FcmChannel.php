<?php

namespace App\Channels;

use App\Services\FCMService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(
        protected FCMService $fcmService
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toFcm($notifiable);

        $tokens = $notifiable->deviceTokens()
            ->pluck('device_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        if (count($tokens) === 1) {
            $this->fcmService->sendToToken($tokens[0], $data);
        } else {
            $this->fcmService->sendToMultipleTokens($tokens, $data);
        }
    }
}

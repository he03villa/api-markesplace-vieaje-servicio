<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FCMService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(storage_path(config('app.firebase_credentials')))
            ->withProjectId(config('app.firebase_project_id'));

        $this->messaging = $factory->createMessaging();
    }

    public function sendToToken(string $token, array $data)
    {
        try {
            $messageData = [
                'token' => $token,
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['body'],
                ],
                'data' => $data['data'] ?? [],
            ];

            if (isset($data['image'])) {
                $messageData['notification']['image'] = $data['image'];
            }

            if (isset($data['web_config'])) {
                $messageData['webpush'] = [
                    'notification' => [
                        'title' => $data['title'],
                        'body' => $data['body'],
                        'icon' => $data['web_config']['icon'] ?? '/icon-192x192.png',
                        'badge' => $data['web_config']['badge'] ?? '/badge-72x72.png',
                        'requireInteraction' => $data['web_config']['require_interaction'] ?? false,
                    ],
                ];

                if (isset($data['web_config']['click_action'])) {
                    $messageData['webpush']['fcm_options'] = [
                        'link' => $data['web_config']['click_action'],
                    ];
                }
            }

            $message = CloudMessage::fromArray($messageData);
            $result = $this->messaging->send($message);

            Log::info('FCM notification sent successfully', [
                'token' => $token,
                'result' => $result,
            ]);

            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'result' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification', [
                'token' => $token,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ];
        }
    }

    public function sendToMultipleTokens(array $tokens, array $data)
    {
        try {
            $messageData = [
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['body'],
                ],
                'data' => $data['data'] ?? [],
            ];

            if (isset($data['image'])) {
                $messageData['notification']['image'] = $data['image'];
            }

            if (isset($data['web_config'])) {
                $messageData['webpush'] = [
                    'notification' => [
                        'title' => $data['title'],
                        'body' => $data['body'],
                        'icon' => $data['web_config']['icon'] ?? '/icon-192x192.png',
                        'badge' => $data['web_config']['badge'] ?? '/badge-72x72.png',
                    ],
                ];

                if (isset($data['web_config']['click_action'])) {
                    $messageData['webpush']['fcm_options'] = [
                        'link' => $data['web_config']['click_action'],
                    ];
                }
            }

            $message = CloudMessage::fromArray($messageData);
            $result = $this->messaging->sendMulticast($message, $tokens);

            Log::info('FCM multicast sent', [
                'tokens_count' => count($tokens),
                'success_count' => $result->successes()->count(),
                'failure_count' => $result->failures()->count(),
            ]);

            return [
                'success' => true,
                'message' => 'Multicast sent successfully',
                'success_count' => $result->successes()->count(),
                'failure_count' => $result->failures()->count(),
                'failures' => $result->failures()->getItems(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send FCM multicast', [
                'tokens_count' => count($tokens),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send multicast: ' . $e->getMessage(),
            ];
        }
    }

    public function sendToTopic(string $topic, array $data)
    {
        try {
            $messageData = [
                'topic' => $topic,
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['body'],
                ],
                'data' => $data['data'] ?? [],
            ];

            if (isset($data['image'])) {
                $messageData['notification']['image'] = $data['image'];
            }

            if (isset($data['android_config'])) {
                $messageData['android'] = $data['android_config'];
            }

            if (isset($data['web_config'])) {
                $messageData['webpush'] = [
                    'notification' => $data['web_config'],
                ];
            }

            $message = CloudMessage::fromArray($messageData);
            $result = $this->messaging->send($message);

            Log::info('FCM topic notification sent', [
                'topic' => $topic,
                'result' => $result,
            ]);

            return [
                'success' => true,
                'message' => 'Topic notification sent successfully',
                'result' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send FCM topic notification', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send topic notification: ' . $e->getMessage(),
            ];
        }
    }

    public function subscribeToTopic(array $tokens, string $topic)
    {
        try {
            $this->messaging->subscribeToTopic($topic, $tokens);

            Log::info('Tokens subscribed to topic', [
                'topic' => $topic,
                'tokens_count' => count($tokens),
            ]);

            return [
                'success' => true,
                'message' => 'Tokens subscribed to topic successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to subscribe tokens to topic', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to subscribe to topic: ' . $e->getMessage(),
            ];
        }
    }

    public function unsubscribeFromTopic(array $tokens, string $topic)
    {
        try {
            $this->messaging->unsubscribeFromTopic($topic, $tokens);

            Log::info('Tokens unsubscribed from topic', [
                'topic' => $topic,
                'tokens_count' => count($tokens),
            ]);

            return [
                'success' => true,
                'message' => 'Tokens unsubscribed from topic successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe tokens from topic', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to unsubscribe from topic: ' . $e->getMessage(),
            ];
        }
    }

    public function validateToken(string $token)
    {
        try {
            $messageData = [
                'token' => $token,
                'data' => ['test' => 'validation'],
            ];

            $message = CloudMessage::fromArray($messageData);
            $this->messaging->validate($message);

            return [
                'success' => true,
                'message' => 'Token is valid',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid token: ' . $e->getMessage(),
            ];
        }
    }
}

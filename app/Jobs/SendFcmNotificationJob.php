<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;

class SendFcmNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $deviceToken,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(): void
    {
        try {
            $messaging = Firebase::messaging();
            $message = [
                'token' => $this->deviceToken,
                'notification' => [
                    'title' => $this->title,
                    'body' => $this->body,
                ],
                'data' => array_map('strval', $this->data),
            ];
            $messaging->send($message);
        } catch (\Throwable $e) {
            Log::warning('FCM send failed: '.$e->getMessage(), [
                'token' => substr($this->deviceToken, 0, 10).'...',
            ]);
        }
    }
}

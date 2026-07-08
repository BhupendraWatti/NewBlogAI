<?php

namespace App\Modules\Operations\Notifications\Channels;

use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericWebhookChannel
{
    public const SETTING_KEY = 'notify_generic_webhook_url';

    public function __construct(
        protected SystemSettingsService $settings
    ) {}

    /**
     * POST the notification payload as JSON to the configured webhook endpoint.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $this->settings->get(self::SETTING_KEY);
        if (empty($url)) {
            return;
        }

        $data = $notification->toArray($notifiable);

        try {
            $response = Http::timeout(10)->post($url, [
                'event' => $data['type'] ?? 'system_notification',
                'data' => $data,
                'sent_at' => now()->toIso8601String(),
            ]);

            if (! $response->successful()) {
                Log::warning('GenericWebhookChannel: delivery failed.', [
                    'status' => $response->status(),
                    'notification' => get_class($notification),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('GenericWebhookChannel: delivery exception: '.$e->getMessage(), [
                'notification' => get_class($notification),
            ]);
        }
    }
}

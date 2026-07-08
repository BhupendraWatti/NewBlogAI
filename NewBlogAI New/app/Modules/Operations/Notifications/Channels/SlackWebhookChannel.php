<?php

namespace App\Modules\Operations\Notifications\Channels;

use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackWebhookChannel
{
    public const SETTING_KEY = 'notify_slack_webhook_url';

    public function __construct(
        protected SystemSettingsService $settings
    ) {}

    /**
     * Send the given notification to the configured Slack incoming webhook.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $this->settings->get(self::SETTING_KEY);
        if (empty($url)) {
            return;
        }

        $text = method_exists($notification, 'toWebhookText')
            ? $notification->toWebhookText($notifiable)
            : json_encode($notification->toArray($notifiable));

        try {
            $response = Http::timeout(10)->post($url, ['text' => $text]);

            if (! $response->successful()) {
                Log::warning('SlackWebhookChannel: delivery failed.', [
                    'status' => $response->status(),
                    'notification' => get_class($notification),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('SlackWebhookChannel: delivery exception: '.$e->getMessage(), [
                'notification' => get_class($notification),
            ]);
        }
    }
}

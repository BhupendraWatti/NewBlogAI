<?php

namespace App\Modules\Operations\Notifications\Channels;

use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhookChannel
{
    public const SETTING_KEY = 'notify_discord_webhook_url';

    public function __construct(
        protected SystemSettingsService $settings
    ) {}

    /**
     * Send the given notification to the configured Discord webhook.
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
            // Discord message content is limited to 2000 characters.
            $response = Http::timeout(10)->post($url, [
                'content' => mb_substr($text, 0, 2000),
            ]);

            if (! $response->successful()) {
                Log::warning('DiscordWebhookChannel: delivery failed.', [
                    'status' => $response->status(),
                    'notification' => get_class($notification),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('DiscordWebhookChannel: delivery exception: '.$e->getMessage(), [
                'notification' => get_class($notification),
            ]);
        }
    }
}

<?php

namespace App\Modules\Operations\Notifications\Concerns;

use App\Modules\Operations\Notifications\Channels\DiscordWebhookChannel;
use App\Modules\Operations\Notifications\Channels\GenericWebhookChannel;
use App\Modules\Operations\Notifications\Channels\SlackWebhookChannel;
use App\Modules\SystemSettings\Services\SystemSettingsService;

trait RoutesToConfiguredChannels
{
    /**
     * Base channels (database + mail) plus any webhook channels whose URLs
     * are configured in system settings.
     */
    protected function configuredChannels(): array
    {
        $settings = app(SystemSettingsService::class);
        $channels = ['database', 'mail'];

        if (! empty($settings->get(SlackWebhookChannel::SETTING_KEY))) {
            $channels[] = SlackWebhookChannel::class;
        }
        if (! empty($settings->get(DiscordWebhookChannel::SETTING_KEY))) {
            $channels[] = DiscordWebhookChannel::class;
        }
        if (! empty($settings->get(GenericWebhookChannel::SETTING_KEY))) {
            $channels[] = GenericWebhookChannel::class;
        }

        return $channels;
    }

    /**
     * Default human-readable single-line text for chat webhooks.
     * Notifications may override for custom formatting.
     */
    public function toWebhookText(object $notifiable): string
    {
        $data = $this->toArray($notifiable);
        $label = ucwords(str_replace('_', ' ', (string) ($data['type'] ?? 'system notification')));

        $details = collect($data)
            ->except('type')
            ->map(function ($value, $key) {
                $rendered = is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value);

                return $key.': '.$rendered;
            })
            ->implode(' | ');

        return '[NewsBlogify] '.$label.($details !== '' ? ' — '.$details : '');
    }
}

<?php

namespace App\Modules\Operations\Notifications;

use App\Modules\Publishing\Models\PublishingLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Modules\Operations\Notifications\Concerns\RoutesToConfiguredChannels;

class PublishingFailedNotification extends Notification
{
    use Queueable, RoutesToConfiguredChannels;

    public function __construct(
        protected PublishingLog $log
    ) {}

    public function via(object $notifiable): array
    {
        // Deliver to both email and database (in-app) channels
        return $this->configuredChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Publishing Failed: '.($this->log->content->title ?? 'Untitled Article'))
            ->line('The publication attempt to WordPress site failed.')
            ->line('Site: '.($this->log->site->domain_url ?? 'N/A'))
            ->line('Error Message: '.$this->log->error_message)
            ->action('View Article', url('/articles/'.$this->log->generated_content_id))
            ->line('Please review the publishing configuration.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'publishing_failed',
            'generated_content_id' => $this->log->generated_content_id,
            'title' => $this->log->content->title ?? 'Untitled Article',
            'site_id' => $this->log->site_id,
            'error_message' => $this->log->error_message,
        ];
    }
}

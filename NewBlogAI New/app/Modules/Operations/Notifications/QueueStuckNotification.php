<?php

namespace App\Modules\Operations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Modules\Operations\Notifications\Concerns\RoutesToConfiguredChannels;

class QueueStuckNotification extends Notification
{
    use Queueable, RoutesToConfiguredChannels;

    public function __construct(
        protected string $queue,
        protected int $pendingCount
    ) {}

    public function via(object $notifiable): array
    {
        return $this->configuredChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Queue Health Alert: Jobs Backlogged')
            ->line("The background queue '{$this->queue}' has a large backlog of pending jobs.")
            ->line("Current Pending Jobs: {$this->pendingCount}")
            ->line('Please verify that the queue worker process is active and running.')
            ->action('View Queue Status', url('/settings/operations'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'queue_stuck',
            'queue' => $this->queue,
            'pending_count' => $this->pendingCount,
        ];
    }
}

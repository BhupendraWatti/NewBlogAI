<?php

namespace App\Modules\Operations\Notifications;

use App\Modules\SubscriptionManager\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Modules\Operations\Notifications\Concerns\RoutesToConfiguredChannels;

class SubscriptionExpiredNotification extends Notification
{
    use Queueable, RoutesToConfiguredChannels;

    public function __construct(
        protected Subscription $subscription
    ) {}

    public function via(object $notifiable): array
    {
        return $this->configuredChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your plan';

        return (new MailMessage)
            ->error()
            ->subject('Subscription Expired — Action Required')
            ->line("Your subscription to {$planName} has expired.")
            ->line('Your account is now in read-only mode. Content generation, scheduling, and publishing are disabled until you renew.')
            ->action('Renew Now', url('/settings/billing'))
            ->line('Contact support if you believe this is an error.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'subscription_expired',
            'subscription_id' => $this->subscription->id,
            'customer_id'     => $this->subscription->customer_id,
            'expired_at'      => now()->toDateTimeString(),
        ];
    }
}

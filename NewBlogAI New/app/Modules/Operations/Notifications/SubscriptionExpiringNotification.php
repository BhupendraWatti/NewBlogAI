<?php

namespace App\Modules\Operations\Notifications;

use App\Modules\SubscriptionManager\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Modules\Operations\Notifications\Concerns\RoutesToConfiguredChannels;

class SubscriptionExpiringNotification extends Notification
{
    use Queueable, RoutesToConfiguredChannels;

    public function __construct(
        protected Subscription $subscription,
        protected int $daysRemaining
    ) {}

    public function via(object $notifiable): array
    {
        return $this->configuredChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your current plan';

        return (new MailMessage)
            ->subject("Subscription Expiring in {$this->daysRemaining} Days")
            ->line("Your subscription to {$planName} will expire in {$this->daysRemaining} day(s).")
            ->line('Renew now to avoid service interruption.')
            ->action('Renew Subscription', url('/settings/billing'))
            ->line('If you have any questions, please contact our support team.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'subscription_expiring',
            'subscription_id' => $this->subscription->id,
            'customer_id'     => $this->subscription->customer_id,
            'days_remaining'  => $this->daysRemaining,
            'expires_at'      => ($this->subscription->trial_ends_at ?? $this->subscription->ends_at)?->toDateString(),
        ];
    }
}

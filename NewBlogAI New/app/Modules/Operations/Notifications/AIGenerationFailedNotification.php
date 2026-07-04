<?php

namespace App\Modules\Operations\Notifications;

use App\Modules\ContentPipeline\Models\PipelineRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AIGenerationFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected PipelineRun $run
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('AI Generation Failed')
            ->line('Content pipeline execution has failed during AI generation.')
            ->line('Pipeline Run ID: '.$this->run->id)
            ->line('Error Message: '.$this->run->error_message)
            ->action('View Pipeline', url('/pipelines/'.$this->run->pipeline_id))
            ->line('Please verify provider availability and configuration.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ai_generation_failed',
            'pipeline_run_id' => $this->run->id,
            'pipeline_id' => $this->run->pipeline_id,
            'error_message' => $this->run->error_message,
        ];
    }
}

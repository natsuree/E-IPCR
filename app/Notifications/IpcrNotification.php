<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Workflow event message. The in-app copy is written immediately; the email
 * goes through the queue. The link is kept as a route name and parameters.
 */
class IpcrNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $message,
        public string $route,
        public array $params = [],
        public string $subject = 'E-IPCR update',
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** Save the in-app notification right away; only the email waits for the queue worker. */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'route' => $this->route,
            'params' => $this->params,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message)
            ->action('Open E-IPCR', route($this->route, $this->params))
            ->line('Mariano Marcos State University · E-IPCR');
    }
}

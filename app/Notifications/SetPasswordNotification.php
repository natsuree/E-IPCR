<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when the Administrator creates or imports an account, or resends the link. */
class SetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Your E-IPCR account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An E-IPCR account was created for you at Mariano Marcos State University. Set your password to sign in.')
            ->action('Set your password', $url)
            ->line("This link expires in {$minutes} minutes. If it expires, use \"Forgot password\" on the sign-in page or ask the Administrator to send a new link.");
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendPasswordResetOtp extends Notification
{
    use Queueable;

    protected string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset Code - SpaceDigital')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You requested to reset your password.')
            ->line('Your password reset code is:')
            ->line('**' . $this->code . '**')
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request a password reset, please ignore this email.')
            ->salutation('Best regards, SpaceDigital Team');
    }
}

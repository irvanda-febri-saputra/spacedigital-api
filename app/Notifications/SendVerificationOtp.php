<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendVerificationOtp extends Notification
{
    // Removed Queueable - send OTP immediately without queue

    protected string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SpaceDigital - Verification Code')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your verification code is:')
            ->line('**' . $this->code . '**')
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}

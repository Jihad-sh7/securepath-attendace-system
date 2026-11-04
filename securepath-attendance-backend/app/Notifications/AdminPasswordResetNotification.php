<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $temporaryPassword) {}
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('SecurePath password reset')->line('An administrator reset your password.')->line('Temporary password: '.$this->temporaryPassword)->line('Change it immediately after signing in.'); }
    public function toArray(object $notifiable): array { return ['title' => 'Password reset', 'message' => 'Your password was reset by a Super Admin.', 'tone' => 'warning']; }
}


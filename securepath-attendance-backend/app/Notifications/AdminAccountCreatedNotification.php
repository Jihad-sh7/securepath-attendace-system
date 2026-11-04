<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $temporaryPassword) {}
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('Your SecurePath admin account')->line('Your administrator account has been created.')->line('Temporary password: '.$this->temporaryPassword)->line('Change this password immediately after signing in.'); }
    public function toArray(object $notifiable): array { return ['title' => 'Admin account created', 'message' => 'Your administrator account is ready.', 'tone' => 'success']; }
}


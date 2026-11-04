<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SystemActionNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $title, private readonly string $message, private readonly string $tone = 'info', private readonly array $context = []) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array { return ['title' => $this->title, 'message' => $this->message, 'tone' => $this->tone, 'context' => $this->context]; }
}


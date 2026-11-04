<?php

namespace App\Listeners;

use App\Events\AlertCreated;
use App\Models\User;
use App\Notifications\SystemActionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAlertReviewers implements ShouldQueue
{
    public function handle(AlertCreated $event): void
    {
        User::where('role', 'super_admin')->orWhereHas('permissions', fn ($query) => $query->where('name', 'alerts.view'))
            ->each(fn (User $user) => $user->notify(new SystemActionNotification($event->alert->title, $event->alert->message, $event->alert->severity->value === 'critical' ? 'danger' : 'warning', ['alertId' => $event->alert->alert_code])));
    }
}


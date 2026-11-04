<?php

namespace App\Listeners;

use App\Events\RecognitionPendingVerification;
use App\Models\User;
use App\Notifications\SystemActionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRecognitionReviewers implements ShouldQueue
{
    public function handle(RecognitionPendingVerification $event): void
    {
        User::where('role', 'super_admin')->orWhereHas('permissions', fn ($query) => $query->whereIn('name', ['attendance.approve', 'biometrics.manage']))
            ->each(fn (User $user) => $user->notify(new SystemActionNotification('Recognition requires review', $event->recognitionEvent->recognition_code.' requires verification.', 'warning', ['recognitionEventId' => $event->recognitionEvent->recognition_code])));
    }
}


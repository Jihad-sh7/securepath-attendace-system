<?php

namespace App\Events;

use App\Models\RecognitionEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecognitionPendingVerification
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly RecognitionEvent $recognitionEvent) {}
}


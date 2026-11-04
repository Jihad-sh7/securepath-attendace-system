<?php

namespace App\Console\Commands;

use App\Services\AutoCheckoutService;
use Illuminate\Console\Command;

class AutoCheckoutAttendance extends Command
{
    protected $signature = 'attendance:auto-checkout';
    protected $description = 'Close open attendance records at their department overtime deadline';

    public function handle(AutoCheckoutService $service): int
    {
        $result = $service->process(now());
        $this->info("Checked {$result['checked']} open records; auto-checked out {$result['closed']}; skipped {$result['skipped']} without an active rule.");
        return self::SUCCESS;
    }
}

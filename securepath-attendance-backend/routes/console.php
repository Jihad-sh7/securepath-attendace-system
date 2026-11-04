<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();
Schedule::command('attendance:auto-checkout')->everyMinute()->withoutOverlapping();

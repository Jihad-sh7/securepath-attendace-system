<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Excused = 'excused';
    case EarlyDeparture = 'early_departure';
    case MissingCheckout = 'missing_checkout';
    case Review = 'review';
}


<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case Mobile = 'mobile';
    case System = 'system';
    case Dashboard = 'dashboard';
}

